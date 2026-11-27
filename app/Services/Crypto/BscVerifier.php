<?php

namespace App\Services\Crypto;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Checks one BEP20 USDT transaction on a public BSC node. Public nodes disable
 * eth_getLogs, so customers submit their hash and only that receipt is read.
 */
class BscVerifier
{
    /** keccak256("Transfer(address,address,uint256)") */
    private const string TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    /**
     * @return array{status: 'ok'|'pending'|'invalid', amount?: numeric-string, from?: string, time?: CarbonImmutable}
     */
    public function verify(string $hash): array
    {
        if (preg_match('/^0x[0-9a-fA-F]{64}$/', $hash) !== 1) {
            return ['status' => 'invalid'];
        }

        $hash = strtolower($hash);
        $config = (array) config('crypto.networks.bep20');
        $receipt = $this->call('eth_getTransactionReceipt', [$hash]);

        if ($receipt === null) {
            return ['status' => 'pending'];
        }

        $receipt = (array) $receipt;

        if (($receipt['status'] ?? null) !== '0x1') {
            return ['status' => 'invalid'];
        }

        $blockNumber = (string) $receipt['blockNumber'];
        $head = (int) Hex::toDec((string) $this->call('eth_blockNumber', []));

        if ($head - (int) Hex::toDec($blockNumber) < (int) $config['confirmations']) {
            return ['status' => 'pending'];
        }

        $total = '0';
        $from = null;

        foreach ((array) ($receipt['logs'] ?? []) as $log) {
            $log = (array) $log;
            $topics = array_map('strval', (array) ($log['topics'] ?? []));

            if (strtolower((string) ($log['address'] ?? '')) !== $config['contract']
                || ($topics[0] ?? null) !== self::TRANSFER_TOPIC
                || count($topics) < 3
                || '0x'.strtolower(substr($topics[2], -40)) !== $config['address']) {
                continue;
            }

            $from ??= '0x'.strtolower(substr($topics[1], -40));
            $total = bcadd($total, Hex::toDec((string) ($log['data'] ?? '0x0')));
        }

        if (bccomp($total, '0') === 0) {
            return ['status' => 'invalid'];
        }

        $block = (array) $this->call('eth_getBlockByNumber', [$blockNumber, false]);

        return [
            'status' => 'ok',
            'amount' => bcdiv($total, bcpow('10', (string) (int) $config['decimals']), 6),
            'from' => (string) $from,
            'time' => CarbonImmutable::createFromTimestamp((int) Hex::toDec((string) $block['timestamp'])),
        ];
    }

    /**
     * @param  list<mixed>  $params
     */
    private function call(string $method, array $params): mixed
    {
        $response = Http::timeout(15)
            ->retry(2, 500, throw: false)
            ->post((string) config('crypto.networks.bep20.rpc'), [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => $method,
                'params' => $params,
            ]);

        if (! $response->successful() || $response->json('error') !== null) {
            throw new RuntimeException("BSC node error for {$method}: ".json_encode($response->json('error') ?? $response->status()));
        }

        return $response->json('result');
    }
}
