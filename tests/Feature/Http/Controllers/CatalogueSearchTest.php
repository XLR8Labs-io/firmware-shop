<?php

use App\Models\Folder;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CommitsDatabaseWrites;

uses(CommitsDatabaseWrites::class);

afterEach(function () {
    Schema::disableForeignKeyConstraints();
    DB::table('products')->truncate();
    DB::table('folders')->truncate();
    Schema::enableForeignKeyConstraints();
});

test('search finds published files by model name or codename', function () {
    Product::factory()->published()->create(['title' => 'Redmi Note 13 Pro Engineering Firmware', 'model_names' => 'garnet, 2312DRA50G']);
    Product::factory()->create(['title' => 'Redmi Note 13 Pro Draft', 'model_names' => 'garnet']);

    $this->get(route('search', ['q' => 'garnet']))
        ->assertOk()
        ->assertSee('Redmi Note 13 Pro Engineering Firmware')
        ->assertDontSee('Redmi Note 13 Pro Draft')
        ->assertSee('1 file found');
});

test('search can be limited to one brand', function () {
    $xiaomi = Folder::factory()->create(['name' => 'Xiaomi']);
    $redmiSeries = Folder::factory()->inside($xiaomi)->create();
    $samsung = Folder::factory()->create(['name' => 'Samsung']);

    Product::factory()->published()->for($redmiSeries)->create(['title' => 'Combination File Redmi']);
    Product::factory()->published()->for($samsung)->create(['title' => 'Combination File Galaxy']);

    $this->get(route('search', ['q' => 'combination', 'brand' => $xiaomi->id]))
        ->assertOk()
        ->assertSee('Combination File Redmi')
        ->assertDontSee('Combination File Galaxy');
});

test('the search term is shown safely', function () {
    $this->get(route('search', ['q' => '<script>alert(1)</script>']))
        ->assertOk()
        ->assertSee('&lt;script&gt;', escape: false)
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

test('an empty search shows the form without results', function () {
    Product::factory()->published()->create(['title' => 'Any Firmware']);

    $this->get(route('search'))
        ->assertOk()
        ->assertSee('Search files')
        ->assertDontSee('Any Firmware');
});
