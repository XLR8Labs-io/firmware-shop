<?php

use App\Models\Folder;
use App\Models\Product;

test('a folder page shows its visible folders and files with a breadcrumb', function () {
    $samsung = Folder::factory()->create(['name' => 'Samsung']);
    $galaxyA = Folder::factory()->inside($samsung)->create(['name' => 'Galaxy A Series']);
    Folder::factory()->inside($galaxyA)->create(['name' => 'Galaxy A15']);
    Folder::factory()->hidden()->inside($galaxyA)->create(['name' => 'Unreleased Model']);
    Product::factory()->published()->for($galaxyA)->create(['title' => 'A Series Tool']);
    Product::factory()->for($galaxyA)->create(['title' => 'Draft Tool']);

    $this->get(route('folders.show', $galaxyA))
        ->assertOk()
        ->assertSeeInOrder(['Home', 'Samsung', 'Galaxy A Series'])
        ->assertSee('Galaxy A15')
        ->assertSee('A Series Tool')
        ->assertDontSee('Unreleased Model')
        ->assertDontSee('Draft Tool');
});

test('hidden folders and everything inside them are not found', function () {
    $hiddenBrand = Folder::factory()->hidden()->create();
    $insideHiddenBrand = Folder::factory()->inside($hiddenBrand)->create();

    $this->get(route('folders.show', $hiddenBrand))->assertNotFound();
    $this->get(route('folders.show', $insideHiddenBrand))->assertNotFound();
});

test('files can be sorted by downloads or price', function () {
    $folder = Folder::factory()->create();
    Product::factory()->published()->paid('40.00')->for($folder)->create(['title' => 'Expensive Rare'])->forceFill(['download_count' => 2])->save();
    Product::factory()->published()->paid('5.00')->for($folder)->create(['title' => 'Cheap Popular'])->forceFill(['download_count' => 500])->save();

    $this->get(route('folders.show', [$folder, 'sort' => 'downloads']))->assertSeeInOrder(['Cheap Popular', 'Expensive Rare']);
    $this->get(route('folders.show', [$folder, 'sort' => 'price']))->assertSeeInOrder(['Cheap Popular', 'Expensive Rare']);
});

test('an unknown sort falls back to newest', function () {
    $folder = Folder::factory()->create();
    Product::factory()->published()->for($folder)->create(['title' => 'Older File', 'published_at' => now()->subDays(3)]);
    Product::factory()->published()->for($folder)->create(['title' => 'Newer File', 'published_at' => now()->subDay()]);

    $this->get(route('folders.show', [$folder, 'sort' => 'id desc; drop table products']))
        ->assertOk()
        ->assertSeeInOrder(['Newer File', 'Older File']);
});
