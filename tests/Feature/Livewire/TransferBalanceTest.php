<?php

use App\Livewire\TransferBalance;
use App\Models\Reseller;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Livewire\Livewire;

function agentWithBalance(string $amount): User
{
    $agent = customerWithBalance($amount);
    Reseller::factory()->withAccount($agent)->create();

    return $agent;
}

test('customers who are not agents cannot open the page or see the menu item', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)->get(route('account.send-balance'))->assertForbidden();
    $this->actingAs($customer)->get(route('dashboard'))->assertDontSee('Send balance');
});

test('an agent checks who receives the money, then sends it', function () {
    $agent = agentWithBalance('100.00');
    $customer = User::factory()->create(['name' => 'Karim Uddin', 'email' => 'karim@example.com']);

    $this->actingAs($agent)->get(route('dashboard'))->assertSee('Send balance');

    Livewire::actingAs($agent)
        ->test(TransferBalance::class)
        ->set('email', 'karim@example.com')
        ->set('amount', '20')
        ->call('review')
        ->assertSee('Karim Uddin')
        ->assertSee('Send now')
        ->call('send')
        ->assertSee('Sent $20.00 to karim@example.com.');

    expect(app(WalletService::class)->balance($customer))->toBe('20.00')
        ->and(app(WalletService::class)->balance($agent))->toBe('80.00');
});

test('changing the amount after the check sends nothing until it is checked again', function () {
    $agent = agentWithBalance('100.00');
    $customer = User::factory()->create(['email' => 'karim@example.com']);

    Livewire::actingAs($agent)
        ->test(TransferBalance::class)
        ->set('email', 'karim@example.com')
        ->set('amount', '20')
        ->call('review')
        ->set('amount', '90')
        ->call('send')
        ->assertSee('Send $90 to');

    expect(app(WalletService::class)->balance($customer))->toBe('0.00');
});

test('an unknown email or a too-large amount is explained', function () {
    $agent = agentWithBalance('10.00');
    User::factory()->create(['email' => 'karim@example.com']);

    Livewire::actingAs($agent)
        ->test(TransferBalance::class)
        ->set('email', 'nobody@example.com')
        ->set('amount', '5')
        ->call('review')
        ->assertHasErrors('email')
        ->set('email', 'karim@example.com')
        ->set('amount', '15')
        ->call('review')
        ->call('send')
        ->assertHasErrors('amount')
        ->assertSee('Your balance is $10.00.');
});
