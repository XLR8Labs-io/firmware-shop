<?php

use App\Models\Folder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('folders with the same name get different slugs', function () {
    $samsung = Folder::factory()->create(['name' => 'Other Models']);
    $xiaomi = Folder::factory()->create(['name' => 'Other Models']);

    expect([$samsung->slug, $xiaomi->slug])->toBe(['other-models', 'other-models-2']);
});

test('replacing a folder image deletes the old file', function () {
    Storage::fake('public');
    $oldImage = UploadedFile::fake()->image('old.png')->store('folders', 'public');
    $newImage = UploadedFile::fake()->image('new.png')->store('folders', 'public');
    $folder = Folder::factory()->create(['image_path' => $oldImage]);

    $folder->update(['image_path' => $newImage]);

    Storage::disk('public')->assertMissing($oldImage);
    Storage::disk('public')->assertExists($newImage);
});

test('deleting a folder deletes its image', function () {
    Storage::fake('public');
    $image = UploadedFile::fake()->image('brand.png')->store('folders', 'public');
    $folder = Folder::factory()->create(['image_path' => $image]);

    $folder->delete();

    Storage::disk('public')->assertMissing($image);
});
