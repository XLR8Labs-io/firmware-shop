<?php

use App\Enums\PurchaseStatus;
use App\Filament\Resources\Purchases\Pages\ManagePurchases;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Product;
use App\Models\User;
use App\Services\Wallet\PurchaseService;
use App\Services\Wallet\WalletService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

test('customers cannot open the purchase list', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(PurchaseResource::getUrl('index'))
        ->assertForbidden();
});

test('an admin refunds a purchase, and it cannot be refunded twice', function () {
    actingAsAdmin();
    $customer = customerWithBalance('40.00');
    $purchase = app(PurchaseService::class)->buy($customer, Product::factory()->published()->paid('40.00')->create(), '40.00');

    Livewire::test(ManagePurchases::class)
        ->callAction(TestAction::make('refund')->table($purchase), data: ['note' => 'Wrong model'])
        ->assertNotified('Refunded $40.00');

    expect($purchase->refresh()->status)->toBe(PurchaseStatus::Refunded)
        ->and(app(WalletService::class)->balance($customer))->toBe('40.00');

    Livewire::test(ManagePurchases::class)
        ->assertActionHidden(TestAction::make('refund')->table($purchase));
});
