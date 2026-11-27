<?php

use App\Filament\Resources\Folders\FolderResource;
use App\Filament\Resources\Folders\Pages\CreateFolder;
use App\Filament\Resources\Folders\Pages\EditFolder;
use App\Filament\Resources\Folders\Pages\ListFolders;
use App\Models\Folder;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

test('customers cannot open the folder list', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(FolderResource::getUrl('index'))
        ->assertForbidden();
});

test('an admin creates a folder inside a brand', function () {
    actingAsAdmin();
    $brand = Folder::factory()->create(['name' => 'Samsung']);

    Livewire::test(CreateFolder::class)
        ->fillForm(['name' => 'Galaxy A Series', 'parent_id' => $brand->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('folders', [
        'name' => 'Galaxy A Series',
        'parent_id' => $brand->id,
        'slug' => 'galaxy-a-series',
        'is_visible' => true,
    ]);
});

test('a folder cannot be moved inside one of its own folders', function () {
    actingAsAdmin();
    $brand = Folder::factory()->create();
    $series = Folder::factory()->inside($brand)->create();

    Livewire::test(EditFolder::class, ['record' => $brand->getRouteKey()])
        ->fillForm(['parent_id' => $series->id])
        ->call('save')
        ->assertHasFormErrors(['parent_id']);

    expect($brand->refresh()->parent_id)->toBeNull();
});

test('only an empty folder can be deleted', function () {
    actingAsAdmin();
    $withFile = Folder::factory()->create();
    Product::factory()->for($withFile)->create();
    $withSubfolder = Folder::factory()->create();
    Folder::factory()->inside($withSubfolder)->create();
    $empty = Folder::factory()->create();

    Livewire::test(ListFolders::class)
        ->assertActionDisabled(TestAction::make('delete')->table($withFile))
        ->assertActionDisabled(TestAction::make('delete')->table($withSubfolder))
        ->assertActionEnabled(TestAction::make('delete')->table($empty));
});
