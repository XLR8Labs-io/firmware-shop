<?php

use App\Livewire\AddFunds;
use App\Models\MfsDeposit;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    openMfsDeposits();
});

test('the page shows how to pay and previews the dollars', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(AddFunds::class)
        ->assertSee('01711000000')
        ->assertSee('Send Money')
        ->assertSee('1 USD = 122.5 BDT')
        ->set('amountBdt', '1225')
        ->assertSee('You will receive $10.00 in your wallet.')
        ->set('method', 'nagad')
        ->assertSee('01811000000')
        ->assertSee('Payment');
});

test('a customer submits a deposit and sees it waiting for a check', function () {
    $customer = User::factory()->create();

    Livewire::actingAs($customer)
        ->test(AddFunds::class)
        ->set('amountBdt', '1225')
        ->set('senderNumber', '01712345678')
        ->set('trxId', 'ab12cd34')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('We will check TrxID AB12CD34 and add $10.00')
        ->assertSee('Waiting for check');

    expect(MfsDeposit::query()->sole()->user_id)->toBe($customer->id);
});

test('the sender number and TrxID must look right', function (array $data, string $field) {
    Livewire::actingAs(User::factory()->create())
        ->test(AddFunds::class)
        ->set(['amountBdt' => '500', 'senderNumber' => '01712345678', 'trxId' => 'TRX12345', ...$data])
        ->call('submit')
        ->assertHasErrors([$field]);

    expect(MfsDeposit::query()->count())->toBe(0);
})->with([
    'number must be 11 digits starting 01' => [['senderNumber' => '+8801712345678'], 'senderNumber'],
    'TrxID too short' => [['trxId' => 'AB12'], 'trxId'],
    'TrxID letters and digits only' => [['trxId' => 'AB12-CD34'], 'trxId'],
    'amount in taka' => [['amountBdt' => '12.345'], 'amountBdt'],
]);

test('a customer can submit at most five deposits an hour', function () {
    $component = Livewire::actingAs(User::factory()->create())->test(AddFunds::class);

    foreach (range(1, 5) as $number) {
        $component->set(['amountBdt' => '500', 'senderNumber' => '01712345678', 'trxId' => "TRX00000{$number}"])->call('submit')->assertHasNoErrors();
    }

    $component->set(['amountBdt' => '500', 'senderNumber' => '01712345678', 'trxId' => 'TRX0000006'])
        ->call('submit')
        ->assertHasErrors(['trxId']);

    expect(MfsDeposit::query()->count())->toBe(5);
});

test('a shortfall from the Buy box fills in the taka amount', function () {
    // $25.00 at 122.50 is 3062.50 BDT, rounded up so the deposit covers the shortfall.
    Livewire::withQueryParams(['usd' => '25.00'])
        ->actingAs(User::factory()->create())
        ->test(AddFunds::class)
        ->assertSet('amountBdt', '3063');
});

test('with PipraPay set up, a customer pays online and is sent to PipraPay', function () {
    fakePipraPay(['pay.test/api/checkout/redirect' => Http::response(['pp_id' => 'PP777', 'pp_url' => 'https://pay.test/payment/PP777'])]);

    Livewire::actingAs(User::factory()->create())
        ->test(AddFunds::class)
        ->assertSee('Pay online')
        ->assertSee('Or send it yourself and enter the TrxID')
        ->set('amountBdt', '1225')
        ->set('senderNumber', '01712345678')
        ->call('payOnline')
        ->assertRedirect('https://pay.test/payment/PP777');

    expect(MfsDeposit::query()->sole()->gateway_payment_id)->toBe('PP777');
});

test('without PipraPay set up, only the manual form is offered', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(AddFunds::class)
        ->assertDontSee('Pay online')
        ->assertSee('Submit deposit');
});
