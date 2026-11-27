<?php

use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\DownloadToken;
use App\Models\Product;
use App\Models\User;
use App\Services\Downloads\DownloadNotAllowed;
use App\Services\Downloads\DownloadService;
use App\Services\Wallet\PurchaseNotAllowed;
use App\Services\Wallet\PurchaseService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('a banned customer cannot buy, and is not charged', function () {
    $customer = customerWithBalance('50.00');
    $customer->ban('Shared paid files.');
    $product = Product::factory()->published()->paid('40.00')->create();

    expect(fn () => app(PurchaseService::class)->buy($customer, $product, '40.00'))
        ->toThrow(PurchaseNotAllowed::class, 'Your account cannot buy files: Shared paid files.');

    expect(app(WalletService::class)->balance($customer))->toBe('50.00')
        ->and($customer->ownsProduct($product))->toBeFalse();
});

test('a banned customer gets no download link', function () {
    $customer = User::factory()->banned()->create();
    $product = Product::factory()->published()->create();

    expect(fn () => app(DownloadService::class)->issueLink($customer, $product, '203.0.113.5', null))
        ->toThrow(DownloadNotAllowed::class);

    expect(DownloadToken::query()->count())->toBe(0);
});

test('a link handed out before the ban stops working', function () {
    fakeGoogleDrive();
    $customer = User::factory()->create();
    $product = Product::factory()->published()->create();
    $link = app(DownloadService::class)->issueLink($customer, $product, '203.0.113.5', null);

    $customer->ban('Shared paid files.');

    $this->get(route('downloads.stream', ['token' => $link->token, 'fileName' => $product->file_name]))->assertForbidden();
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'googleapis.com/drive'));
});

test('a banned customer sees why on the file page and the dashboard, with no way to buy', function () {
    $customer = User::factory()->banned('Shared paid files.')->create();
    $product = Product::factory()->published()->paid('40.00')->create();

    $this->actingAs($customer)
        ->get(route('products.show', $product))
        ->assertSee('Your account cannot buy or download files.')
        ->assertSee('Shared paid files.')
        ->assertDontSeeLivewire('buy-product');

    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertSee('Shared paid files.');
});

test('an admin bans a customer with a reason and can lift the ban', function () {
    actingAsAdmin();
    $customer = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $customer->getRouteKey()])
        ->callAction('ban', data: ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);

    expect($customer->refresh()->is_banned)->toBeFalse();

    Livewire::test(ViewUser::class, ['record' => $customer->getRouteKey()])
        ->callAction('ban', data: ['reason' => 'Shared paid files.'])
        ->assertNotified('Customer banned');

    expect($customer->refresh()->is_banned)->toBeTrue()
        ->and($customer->ban_reason)->toBe('Shared paid files.');

    Livewire::test(ViewUser::class, ['record' => $customer->getRouteKey()])
        ->callAction('unban')
        ->assertNotified('Ban lifted');

    expect($customer->refresh()->is_banned)->toBeFalse()
        ->and($customer->ban_reason)->toBeNull();
});

test('admins cannot be banned from the admin panel', function () {
    actingAsAdmin();
    $otherAdmin = User::factory()->admin()->create();

    Livewire::test(ViewUser::class, ['record' => $otherAdmin->getRouteKey()])
        ->assertActionHidden('ban');
});
