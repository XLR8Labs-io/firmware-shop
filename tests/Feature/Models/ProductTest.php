<?php

use App\Enums\ProductStatus;
use App\Models\Folder;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

test('publishing a product records when it was published', function () {
    $this->freezeSecond();
    $product = Product::factory()->create();

    $product->update(['status' => ProductStatus::Published]);

    expect($product->refresh()->published_at?->equalTo(now()))->toBeTrue();
});

test('republishing keeps the original publish date', function () {
    $product = Product::factory()->published()->create();
    $firstPublishedAt = $product->published_at;

    $product->update(['status' => ProductStatus::Hidden]);
    $product->update(['status' => ProductStatus::Published]);

    expect($product->refresh()->published_at?->equalTo($firstPublishedAt))->toBeTrue();
});

test('only published products in visible folders are visible to customers', function () {
    $hiddenBrand = Folder::factory()->hidden()->create();
    $modelInHiddenBrand = Folder::factory()->inside($hiddenBrand)->create();

    $visible = Product::factory()->published()->create();
    Product::factory()->create();
    Product::factory()->hidden()->create();
    Product::factory()->published()->for($modelInHiddenBrand)->create();

    expect(Product::query()->visible()->pluck('id')->all())->toBe([$visible->id]);
});

test('products with the same title get different slugs, even after one is deleted', function () {
    $first = Product::factory()->create(['title' => 'Galaxy A15 Firmware']);
    $first->delete();

    $second = Product::factory()->create(['title' => 'Galaxy A15 Firmware']);
    $third = Product::factory()->create(['title' => 'Galaxy A15 Firmware']);

    expect([$first->slug, $second->slug, $third->slug])
        ->toBe(['galaxy-a15-firmware', 'galaxy-a15-firmware-2', 'galaxy-a15-firmware-3']);
});

test('the file password is encrypted in the database', function () {
    $product = Product::factory()->create(['file_password' => 'unlock-1234']);

    $stored = DB::table('products')->where('id', $product->id)->value('file_password');

    expect($stored)->not->toContain('unlock-1234')
        ->and($product->refresh()->file_password)->toBe('unlock-1234');
});

test('Drive file IDs and the file password never appear in serialized output', function () {
    $product = Product::factory()->create(['file_password' => 'unlock-1234', 'mirror_drive_file_id' => 'mirror-id']);

    expect($product->toArray())->not->toHaveKeys(['drive_file_id', 'mirror_drive_file_id', 'file_password']);
});

test('a product is free only when its price is zero', function (string $price, bool $isFree) {
    expect(Product::factory()->make(['price' => $price])->isFree())->toBe($isFree);
})->with([
    'zero' => ['0.00', true],
    'one cent' => ['0.01', false],
    'forty dollars' => ['40.00', false],
]);
