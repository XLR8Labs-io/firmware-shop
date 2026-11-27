<?php

namespace App\Services\Deposits;

use App\Enums\DepositStatus;
use App\Enums\MfsMethod;
use App\Jobs\SendAdminAlert;
use App\Models\MfsDeposit;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * bKash and Nagad payments automated by the store's own PipraPay (v3) install.
 *
 * PipraPay's webhooks are not signed, so nothing is ever credited from what a webhook
 * says: every payment is looked up again through PipraPay's verify API, and only a
 * completed payment that matches the deposit exactly is credited.
 */
class PipraPayGateway
{
    /**
     * A real bKash or Nagad TrxID; PipraPay stores "--" or a slip link when it has none.
     */
    private const string TRX_ID_PATTERN = '/^[A-Z0-9]{6,20}$/';

    public function __construct(private MfsDepositService $deposits) {}

    public static function isEnabled(): bool
    {
        return filled(config('services.piprapay.base_url')) && filled(config('services.piprapay.api_key'));
    }

    /**
     * Open a deposit and return the PipraPay page where the customer pays it.
     *
     * @throws DepositNotAllowed
     */
    public function checkout(User $user, MfsMethod $method, string $amountBdt, string $mobileNumber): string
    {
        if (! self::isEnabled()) {
            throw DepositNotAllowed::closed($method->getLabel());
        }

        $deposit = $this->deposits->openGatewayDeposit($user, $method, $amountBdt, $mobileNumber);

        try {
            $response = $this->client()->post('/api/checkout/redirect', [
                'full_name' => $user->name,
                'email_address' => $user->email,
                'mobile_number' => $mobileNumber,
                'amount' => $deposit->amount_bdt,
                'currency' => 'BDT',
                'metadata' => ['deposit_id' => $deposit->id],
                'return_url' => route('deposits.piprapay.return', $deposit),
                'webhook_url' => route('webhooks.piprapay'),
            ]);
        } catch (ConnectionException) {
            $response = null;
        }

        $paymentId = $response?->json('pp_id');
        $checkoutUrl = $response?->json('pp_url');

        if ($response === null || ! $response->successful() || ! is_string($paymentId) || $paymentId === ''
            || ! is_string($checkoutUrl) || ! $this->isPipraPayUrl($checkoutUrl)) {
            Log::warning('PipraPay did not start a payment.', ['deposit' => $deposit->id, 'status' => $response?->status(), 'body' => $response?->body()]);
            $this->deposits->reject($deposit, null, 'The online payment could not be started.', notify: false);

            throw DepositNotAllowed::gatewayUnavailable();
        }

        $deposit->update(['gateway_payment_id' => $paymentId]);

        return $checkoutUrl;
    }

    /**
     * Ask PipraPay about the deposit's payment: credit it once completed, close it if
     * cancelled, and hold it for an admin if anything doesn't match.
     *
     * @throws RuntimeException When PipraPay can't be reached or gives no answer.
     */
    public function confirm(MfsDeposit $deposit): MfsDeposit
    {
        if ($deposit->source !== 'piprapay' || $deposit->gateway_payment_id === null || $deposit->status !== DepositStatus::Pending) {
            return $deposit;
        }

        $payment = $this->verify($deposit->gateway_payment_id);

        if (($payment['status'] ?? null) === 'canceled') {
            try {
                return $this->deposits->reject($deposit, null, 'The payment was cancelled.', notify: false);
            } catch (DepositNotAllowed) {
                return $deposit->refresh();
            }
        }

        // "initiated" or "pending": PipraPay has not matched the money to an SMS yet.
        if (($payment['status'] ?? null) !== 'completed') {
            return $deposit;
        }

        $method = self::methodFor($payment['gateway'] ?? null);
        $trxId = strtoupper(trim(is_string($payment['transaction_id'] ?? null) ? $payment['transaction_id'] : ''));
        $problem = $this->problemWith($deposit, $payment, $method, $trxId);

        if ($problem !== null || $method === null) {
            $this->holdForAdmin($deposit, $problem ?? 'The payment method is not bKash or Nagad.');

            return $deposit;
        }

        $sender = $payment['sender'] ?? null;

        try {
            return $this->deposits->settleGatewayDeposit($deposit, $method, $trxId, is_string($sender) && $sender !== '--' ? $sender : null);
        } catch (UniqueConstraintViolationException) {
            // The same TrxID was reported by hand at this very moment; the next check closes this one as a duplicate.
            return $deposit->refresh();
        }
    }

    /**
     * Check unfinished payments from the last two days again, in case a webhook was lost.
     *
     * @return int How many deposits were checked.
     */
    public function confirmOpenDeposits(): int
    {
        $checked = 0;

        MfsDeposit::query()
            ->where('source', 'piprapay')
            ->where('status', DepositStatus::Pending)
            ->whereNotNull('gateway_payment_id')
            ->whereNull('admin_note')
            ->where('created_at', '>=', now()->subDays(2))
            ->orderBy('id')
            ->each(function (MfsDeposit $deposit) use (&$checked): void {
                try {
                    $this->confirm($deposit);
                    $checked++;
                } catch (RuntimeException $exception) {
                    report($exception);
                }
            });

        return $checked;
    }

    /**
     * PipraPay names the gateway the customer chose, such as "bKash Personal".
     */
    public static function methodFor(mixed $gateway): ?MfsMethod
    {
        $gateway = is_string($gateway) ? strtolower($gateway) : '';

        return match (true) {
            str_contains($gateway, 'bkash') => MfsMethod::Bkash,
            str_contains($gateway, 'nagad') => MfsMethod::Nagad,
            default => null,
        };
    }

    /**
     * Why a completed payment can't be credited automatically, or null if it can.
     *
     * @param  array<array-key, mixed>  $payment
     */
    private function problemWith(MfsDeposit $deposit, array $payment, ?MfsMethod $method, string $trxId): ?string
    {
        $amount = $payment['amount'] ?? null;
        $amount = is_int($amount) || is_float($amount) ? (string) $amount : $amount;
        $metadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];

        return match (true) {
            (string) ($payment['pp_id'] ?? '') !== $deposit->gateway_payment_id => 'PipraPay answered for a different payment.',
            (string) ($metadata['deposit_id'] ?? '') !== (string) $deposit->id => 'The payment belongs to a different deposit.',
            ($payment['currency'] ?? null) !== 'BDT' => 'The payment is not in taka.',
            ! is_string($amount) || ! is_numeric($amount) || bccomp($amount, $deposit->amount_bdt, 2) !== 0 => 'PipraPay recorded a different amount than this deposit.',
            $method === null => 'The payment method is not bKash or Nagad.',
            preg_match(self::TRX_ID_PATTERN, $trxId) !== 1 => 'PipraPay gave no bKash or Nagad TrxID for this payment.',
            default => null,
        };
    }

    /**
     * Leave the deposit pending with the reason, and tell the admins once.
     */
    private function holdForAdmin(MfsDeposit $deposit, string $reason): void
    {
        if ($deposit->admin_note === $reason) {
            return;
        }

        $deposit->update(['admin_note' => $reason]);

        SendAdminAlert::dispatch("Online payment for deposit #{$deposit->id} ({$deposit->amount_bdt} BDT, PipraPay {$deposit->gateway_payment_id}) needs a manual check: {$reason}");
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws RuntimeException
     */
    private function verify(string $paymentId): array
    {
        try {
            $response = $this->client()->post('/api/verify-payment', ['pp_id' => $paymentId]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('PipraPay could not be reached.', previous: $exception);
        }

        $payment = $response->json();

        if (! $response->successful() || ! is_array($payment)) {
            throw new RuntimeException("PipraPay returned HTTP {$response->status()} when verifying payment {$paymentId}.");
        }

        return $payment;
    }

    private function isPipraPayUrl(string $url): bool
    {
        return str_starts_with($url, rtrim((string) config('services.piprapay.base_url'), '/').'/');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.piprapay.base_url'), '/'))
            ->withHeaders(['MHS-PIPRAPAY-API-KEY' => (string) config('services.piprapay.api_key')])
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
