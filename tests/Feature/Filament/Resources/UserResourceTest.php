<?php

use App\Enums\LedgerEntryType;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Livewire\Livewire;

test('customers cannot open the user list', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(UserResource::getUrl('index'))
        ->assertForbidden();
});

test('an admin adds money with a reason, recorded against them', function () {
    $admin = actingAsAdmin();
    $customer = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $customer->getRouteKey()])
        ->callAction('addMoney', data: ['amount' => '12.50', 'note' => 'Paid in cash at the shop'])
        ->assertHasNoFormErrors()
        ->assertNotified('Added $12.50');

    $entry = LedgerEntry::query()->sole();

    expect($entry->user_id)->toBe($customer->id)
        ->and($entry->amount)->toBe('12.50')
        ->and($entry->type)->toBe(LedgerEntryType::AdminCredit)
        ->and($entry->note)->toBe('Paid in cash at the shop')
        ->and($entry->created_by)->toBe($admin->id);
});

test('money changes need a positive amount and a reason', function (array $data, string $field) {
    actingAsAdmin();
    $customer = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $customer->getRouteKey()])
        ->callAction('addMoney', data: $data)
        ->assertHasFormErrors([$field]);

    expect(LedgerEntry::query()->count())->toBe(0);
})->with([
    'reason is required' => [['amount' => '10.00', 'note' => ''], 'note'],
    'amount must be more than zero' => [['amount' => '0', 'note' => 'Test'], 'amount'],
    'amount has at most two decimals' => [['amount' => '1.999', 'note' => 'Test'], 'amount'],
]);

test('an admin cannot remove more money than the wallet holds', function () {
    actingAsAdmin();
    $customer = customerWithBalance('5.00');

    Livewire::test(ViewUser::class, ['record' => $customer->getRouteKey()])
        ->callAction('removeMoney', data: ['amount' => '8.00', 'note' => 'Chargeback'])
        ->assertNotified('The balance is only $5.00');

    expect(app(WalletService::class)->balance($customer))->toBe('5.00');
});
