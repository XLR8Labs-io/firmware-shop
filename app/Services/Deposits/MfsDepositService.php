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
        $rate = $this->settings->bdtPerUsd();

        if ($rate === null || blank($this->settings->mfsAccount($method)['number'])) {
            throw DepositNotAllowed::closed($method->getLabel());
        }

        $amountBdt = WalletService::positiveAmount($amountBdt);
        $amountUsd = self::toUsd($amountBdt, $rate);
        $minimum = $this->settings->mfsMinBdt();

        if (bccomp($amountBdt, $minimum, 2) < 0 || bccomp($amountUsd, '0', 2) <= 0) {
            throw DepositNotAllowed::belowMinimum($minimum);
        }

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
     * @throws DepositNotAllowed
     */
    public function reject(MfsDeposit $deposit, User $admin, string $reason): MfsDeposit
    {
        $deposit = DB::transaction(function () use ($deposit, $admin, $reason): MfsDeposit {
            $deposit = MfsDeposit::query()->with('user')->lockForUpdate()->findOrFail($deposit->id);

            if ($deposit->status !== DepositStatus::Pending) {
                throw DepositNotAllowed::alreadyReviewed();
            }

            $deposit->fill([
                'status' => DepositStatus::Rejected,
                'admin_note' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            return $deposit;
        });

        $deposit->user->notify(new DepositReviewed($deposit));

        return $deposit;
    }
}
