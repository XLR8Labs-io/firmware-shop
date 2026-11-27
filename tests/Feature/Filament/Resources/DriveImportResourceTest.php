<?php

use App\Enums\DriveImportStatus;
use App\Filament\Resources\DriveImports\DriveImportResource;
use App\Filament\Resources\DriveImports\Pages\ManageDriveImports;
use App\Jobs\ImportDriveFolder;
use App\Models\DriveImport;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('customers cannot open the Drive imports', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(DriveImportResource::getUrl('index'))
        ->assertForbidden();
});

test('an admin starts an import from a Drive folder link', function () {
    Queue::fake();
    $admin = actingAsAdmin();
    $samsung = Folder::factory()->create();

    Livewire::test(ManageDriveImports::class)
        ->callAction('import', data: [
            'drive_folder' => 'https://drive.google.com/drive/folders/1AbCdEfGhIjK?usp=sharing',
            'parent_folder_id' => $samsung->id,
            'default_price' => '5.00',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Import started');

    $import = DriveImport::query()->sole();

    expect($import->drive_folder_id)->toBe('1AbCdEfGhIjK')
        ->and($import->parent_folder_id)->toBe($samsung->id)
        ->and($import->default_price)->toBe('5.00')
        ->and($import->status)->toBe(DriveImportStatus::Queued)
        ->and($import->user_id)->toBe($admin->id);

    Queue::assertPushed(ImportDriveFolder::class, fn (ImportDriveFolder $job): bool => $job->import->is($import));
});

test('something that is not a Drive folder link is refused', function () {
    Queue::fake();
    actingAsAdmin();

    Livewire::test(ManageDriveImports::class)
        ->callAction('import', data: ['drive_folder' => 'Samsung firmware', 'default_price' => '0.00'])
        ->assertHasFormErrors(['drive_folder']);

    expect(DriveImport::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});
