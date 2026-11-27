<?php

use App\Enums\LedgerEntryType;
use App\Enums\PurchaseStatus;
use App\Models\Folder;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Wallet\InsufficientFunds;
use App\Services\Wallet\PurchaseNotAllowed;
use App\Services\Wallet\PurchaseService;
use App\Services\Wallet\WalletService;

test('buying a file charges its price and gives access', function () {
    $customer = customerWithBalance('50.00');
    $product = Product::factory()->published()->paid('40.00')->create();

    $purchase = app(PurchaseService::class)->buy($customer, $product, '40.00');

    expect($purchase->status)->toBe(PurchaseStatus::Active)
        ->and($purchase->price_paid)->toBe('40.00')
        ->and($purchase->ledgerEntry->amount)->toBe('-40.00')
        ->and($purchase->ledgerEntry->type)->toBe(LedgerEntryType::Purchase)
        ->and($customer->ownsProduct($product))->toBeTrue()
        ->and(app(WalletService::class)->balance($customer))->toBe('10.00');
});

test('buying a file already owned does not charge again', function () {
    $customer = customerWithBalance('100.00');
    $product = Product::factory()->published()->paid('40.00')->create();
    $purchases = app(PurchaseService::class);

    $first = $purchases->buy($customer, $product, '40.00');
    $second = $purchases->buy($customer, $product, '40.00');

    expect($second->id)->toBe($first->id)
        ->and(app(WalletService::class)->balance($customer))->toBe('60.00');
});

test('without enough money nothing is bought or charged', function () {
    $customer = customerWithBalance('30.00');
    $product = Product::factory()->published()->paid('40.00')->create();

    expect(fn () => app(PurchaseService::class)->buy($customer, $product, '40.00'))->toThrow(InsufficientFunds::class);

    expect(Purchase::query()->count())->toBe(0)
        ->and(app(WalletService::class)->balance($customer))->toBe('30.00');
});

test('nothing is charged if the price changed after the customer saw it', function () {
    $customer = customerWithBalance('100.00');
    $product = Product::factory()->published()->paid('40.00')->create();
    $product->update(['price' => '60.00']);

    expect(fn () => app(PurchaseService::class)->buy($customer, $product, '40.00'))
        ->toThrow(PurchaseNotAllowed::class, 'The price has changed to $60.00.');

    expect(app(WalletService::class)->balance($customer))->toBe('100.00');
});

test('files that are free or not on sale cannot be bought', function (Closure $makeProduct) {
    $customer = customerWithBalance('100.00');
    $product = $makeProduct();

    expect(fn () => app(PurchaseService::class)->buy($customer, $product, $product->price))->toThrow(PurchaseNotAllowed::class);

    expect(app(WalletService::class)->balance($customer))->toBe('100.00');
})->with([
    'free' => [fn () => Product::factory()->published()->create()],
    'draft' => [fn () => Product::factory()->paid()->create()],
    'in a hidden folder' => [fn () => Product::factory()->published()->paid()->for(Folder::factory()->hidden())->create()],
]);

test('a refund returns the money once and removes access', function () {
    $admin = User::factory()->admin()->create();
    $customer = customerWithBalance('40.00');
    $product = Product::factory()->published()->paid('40.00')->create();
    $purchases = app(PurchaseService::class);
    $purchase = $purchases->buy($customer, $product, '40.00');

    $purchases->refund($purchase, $admin, 'Wrong file');

    expect($purchase->refresh()->status)->toBe(PurchaseStatus::Refunded)
        ->and($customer->ownsProduct($product))->toBeFalse()
        ->and(app(WalletService::class)->balance($customer))->toBe('40.00')
        ->and(LedgerEntry::query()->where('type', LedgerEntryType::Refund)->sole()->created_by)->toBe($admin->id);

    expect(fn () => $purchases->refund($purchase, $admin))->toThrow(PurchaseNotAllowed::class);
    expect(app(WalletService::class)->balance($customer))->toBe('40.00');
});

test('a refunded file can be bought again and refunded again', function () {
    $admin = User::factory()->admin()->create();
    $customer = customerWithBalance('40.00');
    $product = Product::factory()->published()->paid('40.00')->create();
    $purchases = app(PurchaseService::class);

    $first = $purchases->buy($customer, $product, '40.00');
    $purchases->refund($first, $admin);
    $again = $purchases->buy($customer, $product, '40.00');

    expect($again->id)->toBe($first->id)
        ->and($again->status)->toBe(PurchaseStatus::Active)
        ->and($again->refunded_at)->toBeNull()
        ->and(app(WalletService::class)->balance($customer))->toBe('0.00');

    $purchases->refund($again, $admin);

    expect(LedgerEntry::query()->where('type', LedgerEntryType::Refund)->count())->toBe(2)
        ->and(app(WalletService::class)->balance($customer))->toBe('40.00');
});
