<?php

use App\Enums\FileRequestStatus;
use App\Filament\Resources\FileRequests\FileRequestResource;
use App\Filament\Resources\FileRequests\Pages\ManageFileRequests;
use App\Models\FileRequest;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

test('open requests are counted in the menu and listed first', function () {
    actingAsAdmin();
    $open = FileRequest::factory()->count(2)->create();
    $done = FileRequest::factory()->create(['status' => FileRequestStatus::Done]);

    expect(FileRequestResource::getNavigationBadge())->toBe('2');

    Livewire::test(ManageFileRequests::class)
        ->assertCanSeeTableRecords($open)
        ->assertCanNotSeeTableRecords([$done]);
});

test('an admin answers a request', function () {
    actingAsAdmin();
    $request = FileRequest::factory()->create();

    Livewire::test(ManageFileRequests::class)
        ->callAction(TestAction::make('reply')->table($request), data: [
            'status' => FileRequestStatus::Done->value,
            'admin_reply' => 'Added under Samsung > Galaxy A.',
        ])
        ->assertNotified('Request updated');

    expect($request->refresh()->status)->toBe(FileRequestStatus::Done)
        ->and($request->admin_reply)->toBe('Added under Samsung > Galaxy A.')
        ->and(FileRequestResource::getNavigationBadge())->toBeNull();
});
