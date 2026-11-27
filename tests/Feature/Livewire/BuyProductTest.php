<?php

use App\Livewire\BuyProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Livewire\Livewire;

test('a customer sees the price, their balance and what is left, then buys', function () {
    $customer = customerWithBalance('50.00');
    $product = Product::factory()->published()->paid('40.00')->create();

    Livewire::actingAs($customer)
        ->test(BuyProduct::class, ['product' => $product])
        ->assertSee('Buy for $40.00')
        ->assertSee('$50.00')
        ->assertSee('Balance after buying')
        ->assertSee('$10.00')
        ->call('buy')
        ->assertRedirect(route('products.show', $product));

    expect($customer->ownsProduct($product))->toBeTrue()
        ->and(app(WalletService::class)->balance($customer))->toBe('10.00');
});

test('without enough money the customer is told how much more they need', function () {
    $customer = customerWithBalance('15.00');
    $product = Product::factory()->published()->paid('40.00')->create();

    Livewire::actingAs($customer)
        ->test(BuyProduct::class, ['product' => $product])
        ->assertSee('You need $25.00 more.')
        ->assertDontSee('Confirm and pay')
        ->call('buy')
        ->assertNoRedirect();

    expect($customer->ownsProduct($product))->toBeFalse();
});

test('if the price changes before confirming, nothing is charged and the new price is shown', function () {
    $customer = customerWithBalance('100.00');
    $product = Product::factory()->published()->paid('40.00')->create();

    $component = Livewire::actingAs($customer)->test(BuyProduct::class, ['product' => $product]);
    $product->update(['price' => '60.00']);

    $component->call('buy')
        ->assertNoRedirect()
        ->assertSee('The price has changed to $60.00.')
        ->assertSee('Confirm and pay $60.00');

    expect(app(WalletService::class)->balance($customer))->toBe('100.00');
});

test('a customer who has not confirmed their email cannot buy', function () {
    $customer = User::factory()->unverified()->create();
    $product = Product::factory()->published()->paid('40.00')->create();

    Livewire::actingAs($customer)
        ->test(BuyProduct::class, ['product' => $product])
        ->call('buy')
        ->assertForbidden();

    $this->actingAs($customer)
        ->get(route('products.show', $product))
        ->assertSee('Confirm your email address to buy files.');
});

test('a buyer sees that they own the file and its password', function () {
    $customer = customerWithBalance('40.00');
    $product = Product::factory()->published()->paid('40.00')->create(['file_password' => 'paid-pass-456']);

    Livewire::actingAs($customer)->test(BuyProduct::class, ['product' => $product])->call('buy');

    $this->actingAs($customer)
        ->get(route('products.show', $product))
        ->assertSee('You own this file')
        ->assertSee('paid-pass-456')
        ->assertDontSee('Buy for $40.00');
});
