<?php

use App\Enums\LedgerEntryType;
use App\Models\Product;
use App\Models\User;
use App\Services\Wallet\PurchaseService;
use App\Services\Wallet\WalletService;

test('guests are sent to log in', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['dashboard', 'account.purchases', 'account.wallet']);

test('the dashboard shows the wallet balance and recent purchases', function () {
    $customer = customerWithBalance('50.00');
    $product = Product::factory()->published()->paid('40.00')->create(['title' => 'Galaxy A15 Combination']);
    app(PurchaseService::class)->buy($customer, $product, '40.00');

    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('$10.00')
        ->assertSee('Galaxy A15 Combination');
});

test('my purchases lists only my files, with their passwords', function () {
    $customer = customerWithBalance('50.00');
    $mine = Product::factory()->published()->paid('40.00')->create(['title' => 'My Firmware', 'file_password' => 'my-pass-123']);
    app(PurchaseService::class)->buy($customer, $mine, '40.00');

    $someoneElse = customerWithBalance('50.00');
    $theirs = Product::factory()->published()->paid('40.00')->create(['title' => 'Their Firmware']);
    app(PurchaseService::class)->buy($someoneElse, $theirs, '40.00');

    $this->actingAs($customer)
        ->get(route('account.purchases'))
        ->assertOk()
        ->assertSee('My Firmware')
        ->assertSee('my-pass-123')
        ->assertDontSee('Their Firmware');
});

test('the wallet history lists my entries but never admin reasons', function () {
    $customer = User::factory()->create();
    $admin = User::factory()->admin()->create();
    app(WalletService::class)->credit($customer, '25.00', LedgerEntryType::AdminCredit, 'admin:1', 'Goodwill credit after complaint', $admin);
    app(WalletService::class)->credit(User::factory()->create(), '99.00', LedgerEntryType::AdminCredit, 'admin:2');

    $this->actingAs($customer)
        ->get(route('account.wallet'))
        ->assertOk()
        ->assertSee('Balance: $25.00')
        ->assertSee('Added by admin')
        ->assertSee('+$25.00')
        ->assertDontSee('Goodwill credit after complaint')
        ->assertDontSee('$99.00');
});
