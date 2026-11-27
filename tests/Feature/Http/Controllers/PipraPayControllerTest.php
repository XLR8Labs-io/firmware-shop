<?php

use App\Enums\DepositStatus;
use App\Models\LedgerEntry;
use App\Models\User;
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

test('a webhook sent twice credits the payment once', function () {
    $deposit = openOnlineDeposit();
    fakePipraPay(['pay.test/api/verify-payment' => Http::response(piprapayPayment($deposit))]);

    $this->postJson(route('webhooks.piprapay'), piprapayPayment($deposit))->assertOk();
    $this->postJson(route('webhooks.piprapay'), piprapayPayment($deposit))->assertOk();

    expect($deposit->refresh()->status)->toBe(DepositStatus::Approved)
        ->and(LedgerEntry::query()->count())->toBe(1)
        ->and(app(WalletService::class)->balance($deposit->user))->toBe('10.00');
});

test('a forged webhook credits nothing when PipraPay does not confirm the payment', function (Closure $verifyAnswer) {
    $deposit = openOnlineDeposit();
    fakePipraPay(['pay.test/api/verify-payment' => $verifyAnswer($deposit)]);

    $this->postJson(route('webhooks.piprapay'), piprapayPayment($deposit, ['amount' => 100000, 'status' => 'completed']));

    expect($deposit->refresh()->status)->toBe(DepositStatus::Pending)
        ->and(LedgerEntry::query()->count())->toBe(0);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pay.test/api/verify-payment');
})->with([
    'not paid at PipraPay' => [fn ($deposit) => Http::response(piprapayPayment($deposit, ['status' => 'initiated', 'transaction_id' => '--']))],
    'unknown to PipraPay' => [fn () => Http::response(['error' => ['code' => 'INVALID_PP_ID']], 400)],
]);

test('a webhook for a payment the store never started is ignored without calling PipraPay', function () {
    fakePipraPay();

    $this->postJson(route('webhooks.piprapay'), ['pp_id' => 'UNKNOWN', 'status' => 'completed'])->assertOk();

    Http::assertNothingSent();
});

test('a customer coming back from PipraPay sees the result', function () {
    $deposit = openOnlineDeposit();
    fakePipraPay(['pay.test/api/verify-payment' => Http::response(piprapayPayment($deposit))]);

    $this->actingAs($deposit->user)
        ->get(route('deposits.piprapay.return', $deposit))
        ->assertRedirect(route('account.add-funds', ['method' => 'bkash']))
        ->assertSessionHas('deposit_notice', 'Payment received. $10.00 was added to your wallet.');
});

test('only the deposit’s owner can use its return page', function () {
    $deposit = openOnlineDeposit();
    fakePipraPay();

    $this->actingAs(User::factory()->create())
        ->get(route('deposits.piprapay.return', $deposit))
        ->assertNotFound();

    Http::assertNothingSent();
});
