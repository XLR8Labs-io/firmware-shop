<?php

use App\Enums\DepositStatus;
use App\Enums\MfsMethod;
use App\Jobs\SendAdminAlert;
use App\Models\LedgerEntry;
use App\Models\MfsDeposit;
use App\Models\User;
use App\Notifications\DepositReviewed;
use App\Services\Deposits\DepositNotAllowed;
use App\Services\Deposits\MfsDepositService;
use App\Services\Deposits\PipraPayGateway;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    openMfsDeposits();
});

test('checkout opens a deposit at PipraPay and returns its payment page', function () {
    fakePipraPay(['pay.test/api/checkout/redirect' => Http::response(['pp_id' => 'PP777', 'pp_url' => 'https://pay.test/payment/PP777'])]);
    $customer = User::factory()->create(['name' => 'Karim', 'email' => 'karim@example.com']);

    $url = app(PipraPayGateway::class)->checkout($customer, MfsMethod::Bkash, '1225', '01712345678');

    $deposit = MfsDeposit::query()->sole();

    expect($url)->toBe('https://pay.test/payment/PP777')
        ->and($deposit->gateway_payment_id)->toBe('PP777')
        ->and($deposit->source)->toBe('piprapay')
        ->and($deposit->trx_id)->toBeNull()
        ->and($deposit->amount_usd)->toBe('10.00');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pay.test/api/checkout/redirect'
        && $request->hasHeader('MHS-PIPRAPAY-API-KEY', 'test-api-key')
        && $request['email_address'] === 'karim@example.com'
        && $request['mobile_number'] === '01712345678'
        && $request['currency'] === 'BDT'
        && $request['metadata'] === ['deposit_id' => $deposit->id]
        && $request['return_url'] === route('deposits.piprapay.return', $deposit)
        && $request['webhook_url'] === route('webhooks.piprapay'));
});

test('if PipraPay refuses or answers strangely, the deposit is closed quietly and nothing is charged', function (array $answer) {
    fakePipraPay(['pay.test/api/checkout/redirect' => Http::response(...$answer)]);

    expect(fn () => app(PipraPayGateway::class)->checkout(User::factory()->create(), MfsMethod::Bkash, '1225', '01712345678'))
        ->toThrow(DepositNotAllowed::class);

    expect(MfsDeposit::query()->sole()->status)->toBe(DepositStatus::Rejected);
    Notification::assertNothingSent();
})->with([
    'an error' => [[['error' => ['code' => 'INVALID_API_KEY']], 400]],
    'a payment page on another site' => [[['pp_id' => 'PP777', 'pp_url' => 'https://evil.test/pay']]],
]);

test('a completed payment is credited once, with the verified TrxID and the gateway the customer chose', function () {
    $deposit = openOnlineDeposit();
    fakePipraPay(['pay.test/api/verify-payment' => Http::response(piprapayPayment($deposit, ['gateway' => 'Nagad Personal', 'transaction_id' => 'ng7k2x9q']))]);
    $gateway = app(PipraPayGateway::class);

    $gateway->confirm($deposit);
    $gateway->confirm($deposit->refresh());

    expect($deposit->refresh()->status)->toBe(DepositStatus::Approved)
        ->and($deposit->method)->toBe(MfsMethod::Nagad)
        ->and($deposit->trx_id)->toBe('NG7K2X9Q')
        ->and(LedgerEntry::query()->sole()->reference)->toBe('nagad:NG7K2X9Q')
        ->and(app(WalletService::class)->balance($deposit->user))->toBe('10.00');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pay.test/api/verify-payment' && $request['pp_id'] === 'PP123');
    Notification::assertSentToTimes($deposit->user, DepositReviewed::class, 1);
});

test('a payment PipraPay has not confirmed yet is left waiting, even with a TrxID the customer typed', function (string $status, string $trxId) {
    $deposit = openOnlineDeposit();
    fakePipraPay(['pay.test/api/verify-payment' => Http::response(piprapayPayment($deposit, ['status' => $status, 'transaction_id' => $trxId]))]);

    app(PipraPayGateway::class)->confirm($deposit);

    expect($deposit->refresh()->status)->toBe(DepositStatus::Pending)
        ->and($deposit->admin_note)->toBeNull()
        ->and(LedgerEntry::query()->count())->toBe(0);
    Queue::assertNotPushed(SendAdminAlert::class);
})->with([
    'not paid yet' => ['initiated', '--'],
    'TrxID not yet matched to an SMS' => ['pending', 'BKX9QW2E7T'],
]);

test('a cancelled payment closes the deposit without emailing the customer', function () {
    $deposit = openOnlineDeposit();
    fakePipraPay(['pay.test/api/verify-payment' => Http::response(piprapayPayment($deposit, ['status' => 'canceled']))]);

    app(PipraPayGateway::class)->confirm($deposit);

    expect($deposit->refresh()->status)->toBe(DepositStatus::Rejected)
        ->and($deposit->admin_note)->toBe('The payment was cancelled.');
    Notification::assertNothingSent();
});

test('a completed payment that does not match the deposit is held for an admin, who is told once', function (array $overrides) {
    $deposit = openOnlineDeposit();
    fakePipraPay(['pay.test/api/verify-payment' => Http::response(piprapayPayment($deposit, $overrides))]);
    $gateway = app(PipraPayGateway::class);

    $gateway->confirm($deposit);
    $gateway->confirm($deposit->refresh());

    expect($deposit->refresh()->status)->toBe(DepositStatus::Pending)
        ->and($deposit->admin_note)->not->toBeNull()
        ->and(LedgerEntry::query()->count())->toBe(0);
    Queue::assertPushed(SendAdminAlert::class, 1);
})->with([
    'a different amount' => [['amount' => 500]],
    'another currency' => [['currency' => 'USD']],
    'another deposit' => [['metadata' => ['deposit_id' => 999]]],
    'another payment' => [['pp_id' => 'PP999']],
    'Rocket instead of bKash or Nagad' => [['gateway' => 'Rocket Personal']],
    'a slip instead of a TrxID' => [['transaction_id' => 'https://pay.test/pp-media/slip.jpg']],
]);

test('PipraPay being unreachable raises an error and changes nothing', function () {
    $deposit = openOnlineDeposit();
    fakePipraPay(['pay.test/api/verify-payment' => Http::response(['error' => ['code' => 'INVALID_PP_ID']], 400)]);

    expect(fn () => app(PipraPayGateway::class)->confirm($deposit))->toThrow(RuntimeException::class);

    expect($deposit->refresh()->status)->toBe(DepositStatus::Pending);
});

test('a TrxID reported by hand first is not credited again when PipraPay confirms it', function () {
    $customer = User::factory()->create();
    $manual = app(MfsDepositService::class)->submit($customer, MfsMethod::Bkash, '1225', '01712345678', 'BKX9QW2E7T');
    $online = openOnlineDeposit($customer);
    fakePipraPay(['pay.test/api/verify-payment' => Http::response(piprapayPayment($online))]);

    app(PipraPayGateway::class)->confirm($online);
    app(MfsDepositService::class)->approve($manual, User::factory()->admin()->create());

    expect($online->refresh()->status)->toBe(DepositStatus::Rejected)
        ->and($online->admin_note)->toContain("deposit #{$manual->id}")
        ->and(LedgerEntry::query()->count())->toBe(1)
        ->and(app(WalletService::class)->balance($customer))->toBe('10.00');
    Queue::assertPushed(SendAdminAlert::class, fn (SendAdminAlert $job): bool => str_contains($job->message, 'BKX9QW2E7T'));
});

test('a TrxID PipraPay already credited cannot be reported by hand', function () {
    $online = openOnlineDeposit();
    fakePipraPay(['pay.test/api/verify-payment' => Http::response(piprapayPayment($online))]);
    app(PipraPayGateway::class)->confirm($online);

    expect(fn () => app(MfsDepositService::class)->submit($online->user, MfsMethod::Bkash, '1225', '01712345678', 'bkx9qw2e7t'))
        ->toThrow(DepositNotAllowed::class, 'This TrxID has already been submitted.');

    expect(app(WalletService::class)->balance($online->user))->toBe('10.00');
});

test('the scheduled check credits payments whose webhook never arrived, and skips held ones', function () {
    $waiting = openOnlineDeposit();
    $held = app(MfsDepositService::class)->openGatewayDeposit(User::factory()->create(), MfsMethod::Bkash, '500', '01712345678');
    $held->update(['gateway_payment_id' => 'PP456', 'admin_note' => 'PipraPay recorded a different amount than this deposit.']);
    fakePipraPay(['pay.test/api/verify-payment' => Http::response(piprapayPayment($waiting))]);

    expect(app(PipraPayGateway::class)->confirmOpenDeposits())->toBe(1);

    expect($waiting->refresh()->status)->toBe(DepositStatus::Approved)
        ->and($held->refresh()->status)->toBe(DepositStatus::Pending);
    Http::assertSentCount(1);
});
