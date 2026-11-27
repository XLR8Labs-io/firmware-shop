<?php

namespace App\Services\Deposits;

use App\Enums\DepositStatus;
use App\Enums\MfsMethod;
use App\Jobs\SendAdminAlert;
use App\Models\MfsDeposit;
use App\Models\User;
use App\Notifications\DepositReviewed;
use App\Services\SiteSettings;
use App\Services\Wallet\WalletService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * bKash and Nagad deposits reported by customers and checked by an admin.
 */
class MfsDepositService
{
    public function __construct(private WalletService $wallet, private SiteSettings $settings) {}

    /**
     * Dollars credited for an amount in taka, rounded down to the cent.
     *
     * @param  numeric-string  $amountBdt
     * @param  numeric-string  $rate  Taka per dollar.
     * @return numeric-string
     */
    public static function toUsd(string $amountBdt, string $rate): string
    {
        return bcdiv($amountBdt, $rate, 2);
    }

    /**
     * @throws DepositNotAllowed
     */
    public function submit(User $user, MfsMethod $method, string $amountBdt, string $senderNumber, string $trxId): MfsDeposit
    {
        if (blank($this->settings->mfsAccount($method)['number'])) {
            throw DepositNotAllowed::closed($method->getLabel());
        }

        [$amountBdt, $rate, $amountUsd] = $this->amounts($method, $amountBdt);
        $trxId = strtoupper(trim($trxId));

        try {
            $deposit = MfsDeposit::query()->create([
                'user_id' => $user->id,
                'method' => $method,
                'amount_bdt' => $amountBdt,
                'rate_bdt_per_usd' => $rate,
                'amount_usd' => $amountUsd,
                'sender_number' => $senderNumber,
                'trx_id' => $trxId,
                'status' => DepositStatus::Pending,
                'source' => 'manual',
            ]);
        } catch (UniqueConstraintViolationException) {
            throw DepositNotAllowed::alreadySubmitted();
        }

        SendAdminAlert::dispatch("New {$method->getLabel()} deposit #{$deposit->id} to check: {$amountBdt} BDT (\${$amountUsd}) from {$senderNumber}, TrxID {$trxId}.");

        return $deposit;
    }

    /**
     * Record a deposit the customer is about to pay through the payment gateway. It has no
     * TrxID until the gateway confirms the payment.
     *
     * @throws DepositNotAllowed
     */
    public function openGatewayDeposit(User $user, MfsMethod $method, string $amountBdt, string $senderNumber): MfsDeposit
    {
        [$amountBdt, $rate, $amountUsd] = $this->amounts($method, $amountBdt);

        return MfsDeposit::query()->create([
            'user_id' => $user->id,
            'method' => $method,
            'amount_bdt' => $amountBdt,
            'rate_bdt_per_usd' => $rate,
            'amount_usd' => $amountUsd,
            'sender_number' => $senderNumber,
            'status' => DepositStatus::Pending,
            'source' => 'piprapay',
        ]);
    }

    /**
     * Credit a gateway deposit with the TrxID the gateway verified. A TrxID already on
     * another deposit (e.g. reported by hand too) is never credited twice: this deposit
     * is closed as a duplicate instead. Calling it again for a settled deposit does nothing.
     */
    public function settleGatewayDeposit(MfsDeposit $deposit, MfsMethod $method, string $trxId, ?string $senderNumber): MfsDeposit
    {
        $trxId = strtoupper(trim($trxId));
        $duplicateOf = null;

        $deposit = DB::transaction(function () use ($deposit, $method, $trxId, $senderNumber, &$duplicateOf): MfsDeposit {
            $deposit = MfsDeposit::query()->with('user')->lockForUpdate()->findOrFail($deposit->id);

            if ($deposit->status !== DepositStatus::Pending) {
                return $deposit;
            }

            $duplicateOf = MfsDeposit::query()
                ->where('method', $method)
                ->where('trx_id', $trxId)
                ->whereKeyNot($deposit->id)
                ->value('id');

            if ($duplicateOf !== null) {
                $deposit->fill([
                    'status' => DepositStatus::Rejected,
                    'admin_note' => "Duplicate of deposit #{$duplicateOf}: this TrxID was already reported.",
                    'reviewed_at' => now(),
                ])->save();

                return $deposit;
            }

            $deposit->fill([
                'method' => $method,
                'trx_id' => $trxId,
                'sender_number' => $senderNumber ?? $deposit->sender_number,
                'status' => DepositStatus::Approved,
                'reviewed_at' => now(),
            ])->save();

            $this->wallet->credit(
                $deposit->user,
                $deposit->amount_usd,
                $method->ledgerType(),
                "{$method->value}:{$trxId}",
                "{$method->getLabel()} TrxID {$trxId} (paid online)",
            );

            return $deposit;
        });

        if ($duplicateOf !== null) {
            SendAdminAlert::dispatch("Online {$method->getLabel()} payment for deposit #{$deposit->id} used TrxID {$trxId}, already on deposit #{$duplicateOf}. Nothing was credited for it; check both.");
        }

        if ($deposit->wasChanged('status')) {
            $deposit->user->notify(new DepositReviewed($deposit));
        }

        return $deposit;
    }

    /**
     * Check the rate and the minimum, and work out the dollars.
     *
     * @return array{numeric-string, numeric-string, numeric-string} Taka, taka per dollar, dollars.
     *
     * @throws DepositNotAllowed
     */
    private function amounts(MfsMethod $method, string $amountBdt): array
    {
        $rate = $this->settings->bdtPerUsd();

        if ($rate === null) {
            throw DepositNotAllowed::closed($method->getLabel());
        }

        $amountBdt = WalletService::positiveAmount($amountBdt);
        $amountUsd = self::toUsd($amountBdt, $rate);
        $minimum = $this->settings->mfsMinBdt();

        if (bccomp($amountBdt, $minimum, 2) < 0 || bccomp($amountUsd, '0', 2) <= 0) {
            throw DepositNotAllowed::belowMinimum($minimum);
        }

        return [$amountBdt, $rate, $amountUsd];
    }

    /**
     * Credit the wallet once. If the customer sent a different amount, the admin can
     * correct it; dollars are recalculated with the rate saved at submission.
     *
     * @throws DepositNotAllowed
     */
    public function approve(MfsDeposit $deposit, User $admin, ?string $correctedAmountBdt = null): MfsDeposit
    {
        $deposit = DB::transaction(function () use ($deposit, $admin, $correctedAmountBdt): MfsDeposit {
            $deposit = MfsDeposit::query()->with('user')->lockForUpdate()->findOrFail($deposit->id);

            if ($deposit->status !== DepositStatus::Pending) {
                throw DepositNotAllowed::alreadyReviewed();
            }

            if ($deposit->trx_id === null) {
                throw new LogicException("Deposit #{$deposit->id} has no TrxID to approve.");
            }

            if ($correctedAmountBdt !== null) {
                $deposit->amount_bdt = WalletService::positiveAmount($correctedAmountBdt);
                $deposit->amount_usd = self::toUsd($deposit->amount_bdt, $deposit->rate_bdt_per_usd);
            }

            // The same reference PipraPay will use, so one payment is credited once however it arrives.
            $this->wallet->credit(
                $deposit->user,
                $deposit->amount_usd,
                $deposit->method->ledgerType(),
                "{$deposit->method->value}:{$deposit->trx_id}",
                "{$deposit->method->getLabel()} TrxID {$deposit->trx_id}",
                $admin,
            );

            $deposit->fill(['status' => DepositStatus::Approved, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();

            return $deposit;
        });

        $deposit->user->notify(new DepositReviewed($deposit));

        return $deposit;
    }

    /**
     * Close a deposit without crediting it. Without an admin, it was closed automatically,
     * e.g. because the payment was cancelled at the gateway.
     *
     * @param  bool  $notify  Email the customer the reason.
     *
     * @throws DepositNotAllowed
     */
    public function reject(MfsDeposit $deposit, ?User $admin, string $reason, bool $notify = true): MfsDeposit
    {
        $deposit = DB::transaction(function () use ($deposit, $admin, $reason): MfsDeposit {
            $deposit = MfsDeposit::query()->with('user')->lockForUpdate()->findOrFail($deposit->id);

            if ($deposit->status !== DepositStatus::Pending) {
                throw DepositNotAllowed::alreadyReviewed();
            }

            $deposit->fill([
                'status' => DepositStatus::Rejected,
                'admin_note' => $reason,
                'reviewed_by' => $admin?->id,
                'reviewed_at' => now(),
            ])->save();

            return $deposit;
        });

        if ($notify) {
            $deposit->user->notify(new DepositReviewed($deposit));
        }

        return $deposit;
    }
}
