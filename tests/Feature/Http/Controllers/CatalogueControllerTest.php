<?php

use App\Models\Folder;
use App\Models\Product;

test('the home page shows only what customers may see', function () {
    $samsung = Folder::factory()->create(['name' => 'Samsung']);
    Folder::factory()->hidden()->create(['name' => 'Secret Brand']);
    $hiddenSeries = Folder::factory()->hidden()->inside($samsung)->create();

    Product::factory()->published()->featured()->for($samsung)->create(['title' => 'Galaxy A15 Combination']);
    Product::factory()->for($samsung)->create(['title' => 'Draft Firmware']);
    Product::factory()->hidden()->for($samsung)->create(['title' => 'Withdrawn Firmware']);
    Product::factory()->published()->for($hiddenSeries)->create(['title' => 'Firmware In Hidden Folder']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Samsung')
        ->assertSee('Galaxy A15 Combination')
        ->assertSee('Featured')
        ->assertDontSee('Secret Brand')
        ->assertDontSee('Draft Firmware')
        ->assertDontSee('Withdrawn Firmware')
        ->assertDontSee('Firmware In Hidden Folder');
});

test('recent files are listed newest first', function () {
    Product::factory()->published()->create(['title' => 'Older File', 'published_at' => now()->subDays(5)]);
    Product::factory()->published()->create(['title' => 'Newer File', 'published_at' => now()->subDay()]);

    $this->get(route('recent'))
        ->assertOk()
        ->assertSeeInOrder(['Newer File', 'Older File']);
});

test('top files are listed by number of downloads', function () {
    Product::factory()->published()->create(['title' => 'Rarely Downloaded'])->forceFill(['download_count' => 3])->save();
    Product::factory()->published()->create(['title' => 'Often Downloaded'])->forceFill(['download_count' => 900])->save();

    $this->get(route('top'))
        ->assertOk()
        ->assertSeeInOrder(['Often Downloaded', 'Rarely Downloaded'])
        ->assertSee('900 downloads');
});
