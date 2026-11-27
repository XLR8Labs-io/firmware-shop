<?php

use App\Enums\CryptoInvoiceStatus;
use App\Enums\CryptoNetwork;
use App\Jobs\SendAdminAlert;
use App\Livewire\CryptoPayment;
use App\Models\CryptoTransfer;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Notifications\CryptoDepositCredited;
use App\Services\Crypto\CryptoException;
use App\Services\Crypto\InvoiceService;
use App\Services\Crypto\TronScanner;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

const STORE_TRON_ADDRESS = 'TStoreAddress1111111111111111111111';
const USDT_TRON_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

beforeEach(function () {
    config([
        'crypto.networks.trc20.address' => STORE_TRON_ADDRESS,
        'crypto.networks.trc20.api_key' => 'test-key',
        'crypto.networks.bep20.address' => '0x'.str_repeat('ab', 20),
        'crypto.networks.bep20.rpc' => 'https://bsc-node.test',
    ]);
    Queue::fake();
    Notification::fake();
    Http::preventStrayRequests();
});

/**
 * A TronGrid TRC-20 transfer item; the amount is in USDT and converted to its 6 decimals.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tronTransfer(string $hash, string $usdt, int $timestampMs, array $overrides = []): array
{
    return array_replace_recursive([
        'transaction_id' => $hash,
        'token_info' => ['symbol' => 'USDT', 'address' => USDT_TRON_CONTRACT, 'decimals' => 6, 'name' => 'Tether USD'],
        'block_timestamp' => $timestampMs,
        'from' => 'TPayerAddress11111111111111111111111',
        'to' => STORE_TRON_ADDRESS,
        'type' => 'Transfer',
        'value' => bcmul($usdt, '1000000', 0),
    ], $overrides);
}

/**
 * @param  list<array<string, mixed>>  $transfers
 */
function fakeTronGrid(array &$transfers): void
{
    Http::fake(['api.trongrid.io/*' => function () use (&$transfers) {
        return Http::response(['data' => $transfers, 'success' => true, 'meta' => []]);
    }]);
}

function decToHex(string $decimal): string
{
    $hex = '';

    while (bccomp($decimal, '0') > 0) {
        $hex = dechex((int) bcmod($decimal, '16')).$hex;
        $decimal = bcdiv($decimal, '16', 0);
    }

    return '0x'.($hex === '' ? '0' : $hex);
}

/**
 * Fake a public BSC node holding one USDT transfer to the store.
 */
function fakeBscNode(string $hash, string $usdt, int $blockNumber, int $headBlock, int $timestamp): void
{
    $padded = fn (string $address): string => '0x'.str_repeat('0', 24).substr($address, 2);

    Http::fake(['bsc-node.test' => function (Request $request) use ($hash, $usdt, $blockNumber, $headBlock, $timestamp, $padded) {
        return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => match ($request['method']) {
            'eth_getTransactionReceipt' => $request['params'][0] === $hash ? [
                'status' => '0x1',
                'blockNumber' => decToHex((string) $blockNumber),
                'logs' => [[
                    'address' => '0x55d398326f99059ff775485246999027b3197955',
                    'topics' => [
                        '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
                        $padded('0x'.str_repeat('cd', 20)),
                        $padded('0x'.str_repeat('ab', 20)),
                    ],
                    'data' => decToHex(bcmul($usdt, bcpow('10', '18'), 0)),
                ]],
            ] : null,
            'eth_blockNumber' => decToHex((string) $headBlock),
            'eth_getBlockByNumber' => ['timestamp' => decToHex((string) $timestamp)],
        }]);
    }]);
}

test('every reserved pay amount on a network is different, and a full amount is refused', function () {
    $invoices = app(InvoiceService::class);

    $payAmounts = collect(range(1, 99))
        ->map(fn () => $invoices->create(User::factory()->create(), CryptoNetwork::Trc20, '10')->pay_amount);

    expect($payAmounts->unique())->toHaveCount(99)
        ->and($payAmounts->min())->toBe('10.01')
        ->and($payAmounts->max())->toBe('10.99');

    expect(fn () => $invoices->create(User::factory()->create(), CryptoNetwork::Trc20, '10'))
        ->toThrow(CryptoException::class, 'Too many open deposits for this amount.');
});

test('an exact TRC20 payment is found and credited to the invoice owner', function () {
    $customer = User::factory()->create();
    $invoice = app(InvoiceService::class)->create($customer, CryptoNetwork::Trc20, '10');
    $transfers = [tronTransfer('ABC123', $invoice->pay_amount, now()->getTimestampMs())];
    fakeTronGrid($transfers);

    app(TronScanner::class)->scan();

    expect($invoice->refresh()->status)->toBe(CryptoInvoiceStatus::Paid)
        ->and($invoice->tx_hash)->toBe('abc123')
        ->and(app(WalletService::class)->balance($customer))->toBe($invoice->pay_amount)
        ->and(LedgerEntry::query()->sole()->reference)->toBe('trc20:abc123');

    Notification::assertSentTo($customer, CryptoDepositCredited::class);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('TRON-PRO-API-KEY', 'test-key')
        && str_contains($request->url(), '/v1/accounts/'.STORE_TRON_ADDRESS.'/transactions/trc20'));
});

test('overpaying by less than a cent matches; paying less goes to review', function () {
    $invoices = app(InvoiceService::class);
    $over = $invoices->create(User::factory()->create(), CryptoNetwork::Trc20, '20');
    $under = $invoices->create(User::factory()->create(), CryptoNetwork::Trc20, '30');
    $transfers = [
        tronTransfer('over1', bcadd($over->pay_amount, '0.009', 6), now()->getTimestampMs()),
        tronTransfer('under1', bcsub($under->pay_amount, '0.01', 6), now()->getTimestampMs()),
    ];
    fakeTronGrid($transfers);

    app(TronScanner::class)->scan();

    expect($over->refresh()->status)->toBe(CryptoInvoiceStatus::Paid)
        ->and($under->refresh()->status)->toBe(CryptoInvoiceStatus::Pending)
        ->and(CryptoTransfer::query()->where('tx_hash', 'under1')->sole()->crypto_invoice_id)->toBeNull();

    Queue::assertPushed(SendAdminAlert::class, fn (SendAdminAlert $job): bool => str_contains($job->message, 'under1'));
});

test('a late payment within 24 hours still matches an expired invoice', function () {
    $invoice = app(InvoiceService::class)->create(User::factory()->create(), CryptoNetwork::Trc20, '10');
    $this->travel(5)->hours();
    $invoice->update(['status' => CryptoInvoiceStatus::Expired]);
    $transfers = [tronTransfer('late1', $invoice->pay_amount, now()->getTimestampMs())];
    fakeTronGrid($transfers);

    app(TronScanner::class)->scan();

    expect($invoice->refresh()->status)->toBe(CryptoInvoiceStatus::Paid);
});

test('a payment made before the invoice existed does not match it', function () {
    $invoice = app(InvoiceService::class)->create(User::factory()->create(), CryptoNetwork::Trc20, '10');
    $transfers = [tronTransfer('early1', $invoice->pay_amount, now()->subMinutes(30)->getTimestampMs())];
    fakeTronGrid($transfers);

    app(TronScanner::class)->scan();

    expect($invoice->refresh()->status)->toBe(CryptoInvoiceStatus::Pending);
});

test('the same transaction is credited only once', function () {
    $customer = User::factory()->create();
    $invoice = app(InvoiceService::class)->create($customer, CryptoNetwork::Trc20, '10');
    $transfers = [tronTransfer('dup1', $invoice->pay_amount, now()->getTimestampMs())];
    fakeTronGrid($transfers);
    $scanner = app(TronScanner::class);

    $scanner->scan();
    app(InvoiceService::class)->create($customer, CryptoNetwork::Trc20, '10');
    $scanner->scan();

    expect(LedgerEntry::query()->count())->toBe(1)
        ->and(CryptoTransfer::query()->count())->toBe(1);
});

test('fake tokens and transfers to other addresses are ignored', function () {
    $invoice = app(InvoiceService::class)->create(User::factory()->create(), CryptoNetwork::Trc20, '10');
    $transfers = [
        tronTransfer('fake1', $invoice->pay_amount, now()->getTimestampMs(), ['token_info' => ['address' => 'TFakeUsdtContract111111111111111111']]),
        tronTransfer('other1', $invoice->pay_amount, now()->getTimestampMs(), ['to' => 'TSomeoneElse11111111111111111111111']),
    ];
    fakeTronGrid($transfers);

    app(TronScanner::class)->scan();

    expect(CryptoTransfer::query()->count())->toBe(0)
        ->and($invoice->refresh()->status)->toBe(CryptoInvoiceStatus::Pending);
});

test('claiming someone else’s BEP20 payment credits nobody; the payer can still claim it', function () {
    $payer = User::factory()->create();
    $copier = User::factory()->create();
    $invoices = app(InvoiceService::class);
    $payerInvoice = $invoices->create($payer, CryptoNetwork::Bep20, '10');
    $copierInvoice = $invoices->create($copier, CryptoNetwork::Bep20, '10');
    $hash = '0x'.str_repeat('1f', 32);
    fakeBscNode($hash, $payerInvoice->pay_amount, blockNumber: 1000, headBlock: 1100, timestamp: now()->getTimestamp());

    Livewire::actingAs($copier)
        ->test(CryptoPayment::class, ['invoice' => $copierInvoice])
        ->set('txHash', $hash)
        ->call('claim')
        ->assertSee('does not match this deposit');

    expect(app(WalletService::class)->balance($copier))->toBe('0.00')
        ->and($payerInvoice->refresh()->status)->toBe(CryptoInvoiceStatus::Pending);

    Livewire::actingAs($payer)
        ->test(CryptoPayment::class, ['invoice' => $payerInvoice])
        ->set('txHash', $hash)
        ->call('claim');

    expect($payerInvoice->refresh()->status)->toBe(CryptoInvoiceStatus::Paid)
        ->and(app(WalletService::class)->balance($payer))->toBe($payerInvoice->pay_amount);
});

test('a BEP20 payment waits for enough confirmations', function () {
    $customer = User::factory()->create();
    $invoice = app(InvoiceService::class)->create($customer, CryptoNetwork::Bep20, '10');
    $hash = '0x'.str_repeat('2e', 32);
    fakeBscNode($hash, $invoice->pay_amount, blockNumber: 1000, headBlock: 1005, timestamp: now()->getTimestamp());

    Livewire::actingAs($customer)
        ->test(CryptoPayment::class, ['invoice' => $invoice])
        ->set('txHash', $hash)
        ->call('claim')
        ->assertSee('Waiting for network confirmations');

    expect($invoice->refresh()->status)->toBe(CryptoInvoiceStatus::Pending);
});

test('only the owner can open a crypto deposit page', function () {
    $invoice = app(InvoiceService::class)->create(User::factory()->create(), CryptoNetwork::Trc20, '10');

    $this->actingAs(User::factory()->create())
        ->get(route('account.crypto', $invoice))
        ->assertNotFound();
});
