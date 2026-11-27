<?php

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

function searchTitles(string $term): array
{
    return Product::query()->search($term)->orderBy('title')->pluck('title')->all();
}

test('finds products by a word in the title or the model names', function () {
    Product::factory()->create(['title' => 'Redmi Note 13 Pro Engineering Firmware', 'model_names' => 'Redmi Note 13 Pro, 2312DRA50G, garnet']);
    Product::factory()->create(['title' => 'Galaxy A15 Combination File', 'model_names' => 'SM-A155F']);

    expect(searchTitles('garnet'))->toBe(['Redmi Note 13 Pro Engineering Firmware'])
        ->and(searchTitles('Combination'))->toBe(['Galaxy A15 Combination File'])
        ->and(searchTitles('2312DRA50G'))->toBe(['Redmi Note 13 Pro Engineering Firmware']);
});

test('matches the start of a word, so a partial codename is enough', function () {
    Product::factory()->create(['title' => 'Redmi Note 13 Pro Firmware', 'model_names' => 'garnet']);

    expect(searchTitles('garn'))->toBe(['Redmi Note 13 Pro Firmware']);
});

test('every word must match', function () {
    Product::factory()->create(['title' => 'Redmi Note 13 Firmware']);
    Product::factory()->create(['title' => 'Redmi 12 Firmware']);

    expect(searchTitles('redmi note'))->toBe(['Redmi Note 13 Firmware']);
});

test('short words and model numbers with punctuation are matched too', function () {
    Product::factory()->create(['title' => 'Redmi Note 13 Firmware']);
    Product::factory()->create(['title' => 'Redmi Note 12 Firmware']);
    Product::factory()->create(['title' => 'Galaxy A15 Firmware', 'model_names' => 'SM-A155F']);

    expect(searchTitles('note 13'))->toBe(['Redmi Note 13 Firmware'])
        ->and(searchTitles('SM-A155F'))->toBe(['Galaxy A15 Firmware']);
});

test('search operators and wildcards in the input are treated as plain text', function (string $term) {
    Product::factory()->create(['title' => 'Redmi Note 13 Firmware']);

    expect(searchTitles($term))->toBe([]);
})->with(['%', '_', '-redmi', '"redmi', 'redmi*)']);
