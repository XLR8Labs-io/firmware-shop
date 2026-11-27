<?php

use App\Enums\FileRequestStatus;
use App\Livewire\RequestFile;
use App\Models\FileRequest;
use App\Models\User;
use Livewire\Livewire;

test('a guest asks for a file', function () {
    $this->get(route('file-requests.create'))->assertOk()->assertSeeLivewire(RequestFile::class);

    Livewire::test(RequestFile::class)
        ->set('name', 'Rahim')
        ->set('contact', '@rahim_fix')
        ->set('brand', 'Samsung')
        ->set('model', 'SM-A156E')
        ->set('details', 'U3 combination file')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('we have your request');

    $request = FileRequest::query()->sole();

    expect($request->only(['user_id', 'name', 'contact', 'brand', 'model', 'details']))->toBe([
        'user_id' => null,
        'name' => 'Rahim',
        'contact' => '@rahim_fix',
        'brand' => 'Samsung',
        'model' => 'SM-A156E',
        'details' => 'U3 combination file',
    ])->and($request->status)->toBe(FileRequestStatus::Open);
});

test('the name, contact, brand and model are required', function () {
    Livewire::test(RequestFile::class)
        ->call('submit')
        ->assertHasErrors(['name' => 'required', 'contact' => 'required', 'brand' => 'required', 'model' => 'required']);

    expect(FileRequest::query()->count())->toBe(0);
});

test('a bot that fills in the hidden field is thanked but nothing is saved', function () {
    Livewire::test(RequestFile::class)
        ->set('name', 'Bot')
        ->set('contact', 'bot@example.com')
        ->set('brand', 'Spam')
        ->set('model', 'Spam')
        ->set('website', 'https://spam.example')
        ->call('submit')
        ->assertSee('we have your request');

    expect(FileRequest::query()->count())->toBe(0);
});

test('a sixth request within an hour is refused', function () {
    $component = Livewire::test(RequestFile::class)->set('name', 'Rahim')->set('contact', 'rahim@example.com');

    foreach (range(1, 6) as $attempt) {
        $component->set('brand', 'Xiaomi')->set('model', "Model {$attempt}")->call('submit');
    }

    $component->assertHasErrors('model');
    expect(FileRequest::query()->count())->toBe(5);
});

test('signed-in customers see their own requests and replies, not other people’s', function () {
    $customer = User::factory()->create(['name' => 'Karim', 'email' => 'karim@example.com']);
    FileRequest::factory()->for($customer)->create(['model' => 'RMX3999', 'status' => FileRequestStatus::Done, 'admin_reply' => 'Added under Realme.']);
    FileRequest::factory()->create(['model' => 'SOMEONE-ELSE']);

    Livewire::actingAs($customer)
        ->test(RequestFile::class)
        ->assertSet('name', 'Karim')
        ->assertSet('contact', 'karim@example.com')
        ->assertSee('RMX3999')
        ->assertSee('Added under Realme.')
        ->assertDontSee('SOMEONE-ELSE')
        ->set('brand', 'Oppo')
        ->set('model', 'CPH2659')
        ->call('submit');

    expect(FileRequest::query()->where('model', 'CPH2659')->sole()->user_id)->toBe($customer->id);
});
