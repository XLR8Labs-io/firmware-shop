<?php

use App\Enums\DepositStatus;
use App\Enums\LedgerEntryType;
use App\Enums\MfsMethod;
use App\Jobs\SendAdminAlert;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Notifications\DepositReviewed;
use App\Services\Deposits\DepositNotAllowed;
use App\Services\Deposits\MfsDepositService;
use App\Services\SiteSettings;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    openMfsDeposits();
});

test('a deposit is converted at the current rate, rounded down, and the rate is saved', function () {
    $deposit = app(MfsDepositService::class)->submit(User::factory()->create(), MfsMethod::Bkash, '1000', '01712345678', 'ab12cd34ef');

    expect($deposit->amount_bdt)->toBe('1000.00')
        ->and($deposit->rate_bdt_per_usd)->toBe('122.5000')
        ->and($deposit->amount_usd)->toBe('8.16')
        ->and($deposit->trx_id)->toBe('AB12CD34EF')
        ->and($deposit->status)->toBe(DepositStatus::Pending);

    Queue::assertPushed(SendAdminAlert::class, fn (SendAdminAlert $job): bool => str_contains($job->message, 'AB12CD34EF'));
});

test('a TrxID can be submitted only once per method', function () {
    $deposits = app(MfsDepositService::class);
    $deposits->submit(User::factory()->create(), MfsMethod::Bkash, '500', '01712345678', 'TRX123456');

    expect(fn () => $deposits->submit(User::factory()->create(), MfsMethod::Bkash, '500', '01712345678', 'trx123456'))
        ->toThrow(DepositNotAllowed::class, 'This TrxID has already been submitted.');

    expect($deposits->submit(User::factory()->create(), MfsMethod::Nagad, '500', '01812345678', 'TRX123456')->trx_id)->toBe('TRX123456');
});

test('deposits below the minimum or while closed are refused', function () {
    $deposits = app(MfsDepositService::class);

    expect(fn () => $deposits->submit(User::factory()->create(), MfsMethod::Bkash, '99', '01712345678', 'TRX123456'))
        ->toThrow(DepositNotAllowed::class, 'The smallest deposit is 100.00 BDT.');

    app(SiteSettings::class)->save(['bdt_per_usd' => null]);

    expect(fn () => $deposits->submit(User::factory()->create(), MfsMethod::Bkash, '500', '01712345678', 'TRX999999'))
        ->toThrow(DepositNotAllowed::class, 'bKash deposits are not available right now.');
});

test('approving adds the money exactly once and tells the customer', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();
    $deposits = app(MfsDepositService::class);
    $deposit = $deposits->submit($customer, MfsMethod::Bkash, '1225', '01712345678', 'TRX123456');

    $deposits->approve($deposit, $admin);

    expect(fn () => $deposits->approve($deposit, $admin))->toThrow(DepositNotAllowed::class);

    $entry = LedgerEntry::query()->sole();

    expect(app(WalletService::class)->balance($customer))->toBe('10.00')
        ->and($entry->type)->toBe(LedgerEntryType::DepositBkash)
        ->and($entry->reference)->toBe('bkash:TRX123456')
        ->and($entry->created_by)->toBe($admin->id)
        ->and($deposit->refresh()->status)->toBe(DepositStatus::Approved);

    Notification::assertSentTo($customer, DepositReviewed::class);
});

test('a corrected amount is converted at the rate saved with the deposit', function () {
    $customer = User::factory()->create();
    $deposits = app(MfsDepositService::class);
    $deposit = $deposits->submit($customer, MfsMethod::Nagad, '1225', '01812345678', 'TRX123456');
    app(SiteSettings::class)->save(['bdt_per_usd' => '130']);

    $deposits->approve($deposit, User::factory()->admin()->create(), '2450');

    expect($deposit->refresh()->amount_bdt)->toBe('2450.00')
        ->and($deposit->amount_usd)->toBe('20.00')
        ->and(app(WalletService::class)->balance($customer))->toBe('20.00');
});

test('rejecting records the reason, adds nothing and tells the customer', function () {
    $customer = User::factory()->create();
    $deposits = app(MfsDepositService::class);
    $deposit = $deposits->submit($customer, MfsMethod::Bkash, '500', '01712345678', 'TRX123456');

    $deposits->reject($deposit, User::factory()->admin()->create(), 'No payment with this TrxID');

    expect($deposit->refresh()->status)->toBe(DepositStatus::Rejected)
        ->and($deposit->admin_note)->toBe('No payment with this TrxID')
        ->and(LedgerEntry::query()->count())->toBe(0);

    Notification::assertSentTo($customer, DepositReviewed::class);
});
