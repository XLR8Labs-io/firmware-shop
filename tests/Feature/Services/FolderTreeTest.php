<?php

use App\Models\Folder;
use App\Services\FolderTree;

test('a folder inside a hidden folder is hidden too', function () {
    $brand = Folder::factory()->hidden()->create();
    $series = Folder::factory()->inside($brand)->create();
    $model = Folder::factory()->inside($series)->create();
    $otherBrand = Folder::factory()->create();

    $tree = app(FolderTree::class);

    expect($tree->visibleIds())->toBe([$otherBrand->id])
        ->and($tree->isVisible($model->id))->toBeFalse();
});

test('ancestors are listed from the top level down', function () {
    $brand = Folder::factory()->create();
    $series = Folder::factory()->inside($brand)->create();
    $model = Folder::factory()->inside($series)->create();

    expect(app(FolderTree::class)->ancestorIds($model->id))->toBe([$brand->id, $series->id]);
});

test('descendants include the folder and everything inside it', function () {
    $brand = Folder::factory()->create();
    $series = Folder::factory()->inside($brand)->create();
    $model = Folder::factory()->inside($series)->create();
    Folder::factory()->create();

    expect(app(FolderTree::class)->descendantIds($brand->id))
        ->toEqualCanonicalizing([$brand->id, $series->id, $model->id]);
});

test('the tree reflects folder changes straight away', function () {
    $brand = Folder::factory()->create();
    $tree = app(FolderTree::class);
    expect($tree->isVisible($brand->id))->toBeTrue();

    $brand->update(['is_visible' => false]);

    expect($tree->isVisible($brand->id))->toBeFalse();
});
