<?php

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\Wallet\InsufficientFunds;
use App\Services\Wallet\WalletService;

test('the balance is the sum of every entry', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class);

    $wallet->credit($user, '25.50', LedgerEntryType::AdminCredit, 'test:1');
    $wallet->credit($user, '0.25', LedgerEntryType::AdminCredit, 'test:2');
    $wallet->debit($user, '10.75', LedgerEntryType::AdminDebit, 'test:3');

    expect($wallet->balance($user))->toBe('15.00')
        ->and($wallet->balance(User::factory()->create()))->toBe('0.00');
});

test('recording the same credit twice adds the money once', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class);

    $first = $wallet->credit($user, '10.00', LedgerEntryType::AdminCredit, 'bkash:9AB3CD7EF1');
    $repeat = $wallet->credit($user, '10.00', LedgerEntryType::AdminCredit, 'bkash:9AB3CD7EF1');

    expect($repeat->id)->toBe($first->id)
        ->and($wallet->balance($user))->toBe('10.00');
});

test('a reference already used for a different credit is refused', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class);
    $wallet->credit($user, '10.00', LedgerEntryType::AdminCredit, 'bkash:9AB3CD7EF1');

    expect(fn () => $wallet->credit($user, '99.00', LedgerEntryType::AdminCredit, 'bkash:9AB3CD7EF1'))
        ->toThrow(LogicException::class);
    expect(fn () => $wallet->credit(User::factory()->create(), '10.00', LedgerEntryType::AdminCredit, 'bkash:9AB3CD7EF1'))
        ->toThrow(LogicException::class);
});

test('a debit larger than the balance is refused and records nothing', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class);
    $wallet->credit($user, '5.00', LedgerEntryType::AdminCredit, 'test:seed');

    try {
        $wallet->debit($user, '12.50', LedgerEntryType::AdminDebit, 'test:too-much');
        $this->fail('The debit should have been refused.');
    } catch (InsufficientFunds $exception) {
        expect($exception->shortfall())->toBe('7.50');
    }

    expect($wallet->balance($user))->toBe('5.00')
        ->and(LedgerEntry::query()->count())->toBe(1);
});

test('amounts must be positive with at most two decimals', function (string $amount) {
    expect(fn () => app(WalletService::class)->credit(User::factory()->create(), $amount, LedgerEntryType::AdminCredit, 'test:bad'))
        ->toThrow(InvalidArgumentException::class);
})->with(['0', '0.00', '-5.00', '1.999', '1e3', 'ten', '']);

test('ledger entries can never be changed or deleted', function () {
    $entry = app(WalletService::class)->credit(User::factory()->create(), '10.00', LedgerEntryType::AdminCredit, 'test:1');

    expect(fn () => $entry->update(['amount' => '1000.00']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and($entry->fresh()?->amount)->toBe('10.00');
});
