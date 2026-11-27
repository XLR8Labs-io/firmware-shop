<?php

namespace App\Services\Crypto;

use App\Enums\CryptoNetwork;
use App\Models\CryptoTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads confirmed USDT transfers to the store's TRON address from TronGrid
 * (GET /v1/accounts/{address}/transactions/trc20) and matches new ones to invoices.
 */
class TronScanner
{
    public function __construct(private InvoiceMatcher $matcher) {}

    /**
     * @return int How many transfers were seen for the first time.
     */
    public function scan(): int
    {
        $config = (array) config('crypto.networks.trc20');
        $address = CryptoNetwork::Trc20->address();

        if ($address === null) {
            return 0;
        }

        // Derived from stored transfers so it can't be lost; overlap a little and rely on unique hashes.
        $latest = CryptoTransfer::query()->where('network', CryptoNetwork::Trc20)->max('block_time');
        $cursor = $latest !== null
            ? CarbonImmutable::parse((string) $latest)
            : now()->subHours((int) config('crypto.reserve_hours'));

        $query = [
            'only_to' => 'true',
            'only_confirmed' => 'true',
            'contract_address' => $config['contract'],
            'min_timestamp' => $cursor->getTimestampMs() - 300_000,
            'order_by' => 'block_timestamp,asc',
            'limit' => 200,
        ];
        $newTransfers = 0;

        do {
            $response = Http::baseUrl((string) $config['api'])
                ->withHeaders(array_filter(['TRON-PRO-API-KEY' => $config['api_key'] ?? null]))
                ->acceptJson()
                ->timeout(20)
                ->retry(3, fn (int $attempt): int => 1000 * 2 ** $attempt, throw: false)
                ->get("/v1/accounts/{$address}/transactions/trc20", $query);

            if (! $response->successful()) {
                throw new RuntimeException("TronGrid returned HTTP {$response->status()}.");
            }

            foreach ((array) $response->json('data', []) as $item) {
                $item = (array) $item;

                if (($item['to'] ?? null) !== $address
                    || ($item['token_info']['address'] ?? null) !== $config['contract']
                    || ($item['type'] ?? null) !== 'Transfer'
                    || ! is_numeric($item['value'] ?? null)) {
                    continue;
                }

                $amount = bcdiv((string) $item['value'], bcpow('10', (string) (int) $config['decimals']), 6);
                $blockTime = CarbonImmutable::createFromTimestampMs((int) $item['block_timestamp']);
                $txHash = strtolower((string) $item['transaction_id']);

                $transfer = CryptoTransfer::query()->firstOrCreate(['tx_hash' => $txHash], [
                    'network' => CryptoNetwork::Trc20,
                    'from_address' => $item['from'] ?? null,
                    'amount' => $amount,
                    'block_time' => $blockTime,
                ]);

                if ($transfer->wasRecentlyCreated) {
                    $newTransfers++;
                    $this->matcher->match(CryptoNetwork::Trc20, $txHash, $amount, $blockTime);
                }
            }

            $query['fingerprint'] = $response->json('meta.fingerprint');
        } while (is_string($query['fingerprint']) && $query['fingerprint'] !== '');

        return $newTransfers;
    }
}
