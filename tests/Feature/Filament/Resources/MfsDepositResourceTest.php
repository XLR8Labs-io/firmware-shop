<?php

use App\Enums\DepositStatus;
use App\Enums\MfsMethod;
use App\Filament\Resources\MfsDeposits\MfsDepositResource;
use App\Filament\Resources\MfsDeposits\Pages\ManageMfsDeposits;
use App\Models\User;
use App\Services\Deposits\MfsDepositService;
use App\Services\Wallet\WalletService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    openMfsDeposits();
});

test('customers cannot open the deposit queue', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(MfsDepositResource::getUrl('index'))
        ->assertForbidden();
});

test('an admin approves a deposit and the money reaches the wallet', function () {
    actingAsAdmin();
    $customer = User::factory()->create();
    $deposit = app(MfsDepositService::class)->submit($customer, MfsMethod::Bkash, '1225', '01712345678', 'TRX123456');

    Livewire::test(ManageMfsDeposits::class)
        ->assertCanSeeTableRecords([$deposit])
        ->callAction(TestAction::make('approve')->table($deposit), data: ['amount_bdt' => '1225'])
        ->assertNotified('Added $10.00 to the wallet');

    expect($deposit->refresh()->status)->toBe(DepositStatus::Approved)
        ->and(app(WalletService::class)->balance($customer))->toBe('10.00');
});

test('rejecting needs a reason', function () {
    actingAsAdmin();
    $deposit = app(MfsDepositService::class)->submit(User::factory()->create(), MfsMethod::Bkash, '500', '01712345678', 'TRX123456');

    Livewire::test(ManageMfsDeposits::class)
        ->callAction(TestAction::make('reject')->table($deposit), data: ['reason' => ''])
        ->assertHasFormErrors(['reason']);

    expect($deposit->refresh()->status)->toBe(DepositStatus::Pending);
});
