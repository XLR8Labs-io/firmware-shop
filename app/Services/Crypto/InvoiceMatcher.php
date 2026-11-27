<?php

namespace App\Services\Crypto;

use App\Enums\CryptoInvoiceStatus;
use App\Enums\CryptoNetwork;
use App\Enums\LedgerEntryType;
use App\Jobs\SendAdminAlert;
use App\Models\CryptoInvoice;
use App\Models\CryptoTransfer;
use App\Notifications\CryptoDepositCredited;
use App\Services\Wallet\WalletService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Pairs an incoming transfer with the invoice whose reserved amount it pays.
 *
 * The money always goes to the invoice owner, whoever reports the hash; a hash is
 * used once; a payment made before an invoice existed can't match it; and a payment
 * short by any amount, or over by a cent or more, is left for an admin to review.
 */
class InvoiceMatcher
{
    public function __construct(private WalletService $wallet) {}

    /**
     * @param  numeric-string  $amount  The USDT received, to 6 decimals.
     * @param  int|null  $onlyInvoiceId  Restrict to one invoice, when a customer claims a payment.
     */
    public function match(CryptoNetwork $network, string $txHash, string $amount, CarbonInterface $blockTime, ?int $onlyInvoiceId = null): ?CryptoInvoice
    {
        $invoice = DB::transaction(function () use ($network, $txHash, $amount, $blockTime, $onlyInvoiceId): ?CryptoInvoice {
            if (CryptoInvoice::query()->where('tx_hash', $txHash)->exists()) {
                return null;
            }

            $invoice = CryptoInvoice::query()
                ->with('user')
                ->where('network', $network)
                ->whereIn('status', [CryptoInvoiceStatus::Pending, CryptoInvoiceStatus::Expired])
                ->where('created_at', '<=', $blockTime->copy()->addMinutes(2))
                ->where('created_at', '>=', $blockTime->copy()->subHours((int) config('crypto.reserve_hours')))
                ->where('pay_amount', '<=', $amount)
                ->where('pay_amount', '>', bcsub($amount, '0.01', 6))
                ->when($onlyInvoiceId, fn ($query, int $id) => $query->whereKey($id))
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if ($invoice === null) {
                return null;
            }

            $invoice->update([
                'status' => CryptoInvoiceStatus::Paid,
                'tx_hash' => $txHash,
                'received_amount' => $amount,
                'paid_at' => now(),
            ]);

            $this->wallet->credit($invoice->user, $invoice->pay_amount, LedgerEntryType::DepositCrypto, "{$network->value}:{$txHash}", "Crypto invoice #{$invoice->id}");

            CryptoTransfer::query()->where('tx_hash', $txHash)->update(['crypto_invoice_id' => $invoice->id]);

            return $invoice;
        });

        if ($invoice !== null) {
            $invoice->user->notify(new CryptoDepositCredited($invoice));
        } elseif ($onlyInvoiceId === null) {
            SendAdminAlert::dispatch("Unmatched {$network->getLabel()} transfer of {$amount} USDT ({$txHash}). Assign it to a customer in the admin panel.");
        }

        return $invoice;
    }
}
