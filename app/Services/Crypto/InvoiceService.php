<?php

namespace App\Services\Crypto;

use App\Enums\CryptoInvoiceStatus;
use App\Enums\CryptoNetwork;
use App\Models\CryptoInvoice;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Cache;

class InvoiceService
{
    /**
     * @return numeric-string
     */
    public static function minimumDeposit(): string
    {
        $minimum = config('crypto.min_deposit');

        if (is_int($minimum)) {
            return bcadd((string) $minimum, '0', 2);
        }

        return is_string($minimum) && is_numeric($minimum) ? bcadd($minimum, '0', 2) : '5.00';
    }

    /**
     * Create an invoice whose pay amount (the base plus 1–99 cents) no other reserved
     * invoice on the network uses, so the amount alone identifies who paid.
     *
     * @throws CryptoException
     */
    public function create(User $user, CryptoNetwork $network, string $baseAmount): CryptoInvoice
    {
        if ($network->address() === null) {
            throw CryptoException::closed($network->getLabel());
        }

        $base = WalletService::positiveAmount($baseAmount);
        $minimum = self::minimumDeposit();

        if (bccomp($base, $minimum, 2) < 0) {
            throw CryptoException::belowMinimum($minimum);
        }

        // Two customers must never be given the same amount at the same moment.
        return Cache::lock("crypto-invoice-{$network->value}", 10)->block(5, function () use ($user, $network, $base): CryptoInvoice {
            $taken = CryptoInvoice::query()
                ->where('network', $network)
                ->whereIn('status', [CryptoInvoiceStatus::Pending, CryptoInvoiceStatus::Expired])
                ->where('created_at', '>=', now()->subHours((int) config('crypto.reserve_hours')))
                ->whereBetween('pay_amount', [bcadd($base, '0.01', 2), bcadd($base, '0.99', 2)])
                ->pluck('pay_amount')
                ->flip();

            $cents = range(1, 99);
            shuffle($cents);

            foreach ($cents as $cent) {
                $payAmount = bcadd($base, bcdiv((string) $cent, '100', 2), 2);

                if (! $taken->has($payAmount)) {
                    return CryptoInvoice::query()->create([
                        'user_id' => $user->id,
                        'network' => $network,
                        'base_amount' => $base,
                        'pay_amount' => $payAmount,
                        'status' => CryptoInvoiceStatus::Pending,
                        'expires_at' => now()->addMinutes((int) config('crypto.invoice_ttl_minutes')),
                    ]);
                }
            }

            throw CryptoException::noFreeAmount();
        });
    }
}
