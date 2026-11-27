<?php

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Reseller;
use App\Models\User;
use App\Services\Wallet\InsufficientFunds;
use App\Services\Wallet\TransferNotAllowed;
use App\Services\Wallet\TransferService;
use App\Services\Wallet\WalletService;

test('an agent sends balance and both entries share one reference', function () {
    $agent = customerWithBalance('100.00');
    Reseller::factory()->withAccount($agent)->create(['name' => 'Dhaka Mobile Care']);
    $customer = User::factory()->create(['email' => 'karim@example.com']);

    $sent = app(TransferService::class)->send($agent, $customer, '25.50');

    $received = LedgerEntry::query()->where('type', LedgerEntryType::TransferIn)->sole();
    $wallet = app(WalletService::class);

    expect($wallet->balance($agent))->toBe('74.50')
        ->and($wallet->balance($customer))->toBe('25.50')
        ->and($sent->type)->toBe(LedgerEntryType::TransferOut)
        ->and($received->reference)->toBe($sent->reference)
        ->and($sent->note)->toBe('To karim@example.com')
        ->and($received->note)->toBe('From Dhaka Mobile Care');
});

test('sending more than the balance writes nothing', function () {
    $agent = customerWithBalance('10.00');
    Reseller::factory()->withAccount($agent)->create();
    $customer = User::factory()->create();

    expect(fn () => app(TransferService::class)->send($agent, $customer, '10.01'))->toThrow(InsufficientFunds::class);

    expect(LedgerEntry::query()->whereIn('type', [LedgerEntryType::TransferIn, LedgerEntryType::TransferOut])->count())->toBe(0)
        ->and(app(WalletService::class)->balance($customer))->toBe('0.00');
});

test('only an active agent can send, and never to themselves', function (Closure $makeSender, bool $toSelf) {
    $sender = $makeSender();
    $recipient = $toSelf ? $sender : User::factory()->create();

    expect(fn () => app(TransferService::class)->send($sender, $recipient, '5.00'))->toThrow(TransferNotAllowed::class);

    expect(app(WalletService::class)->balance($sender))->toBe('50.00');
})->with([
    'a customer who is not an agent' => [fn () => customerWithBalance('50.00'), false],
    'an inactive agent' => [function () {
        $user = customerWithBalance('50.00');
        Reseller::factory()->inactive()->withAccount($user)->create();

        return $user;
    }, false],
    'an agent sending to their own account' => [function () {
        $user = customerWithBalance('50.00');
        Reseller::factory()->withAccount($user)->create();

        return $user;
    }, true],
]);
