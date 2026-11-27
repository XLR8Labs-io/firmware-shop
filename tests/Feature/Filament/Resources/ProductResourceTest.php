<?php

use App\Enums\ProductStatus;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Folder;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function validProductForm(Folder $folder): array
{
    return [
        'title' => 'Galaxy A15 Combination File',
        'folder_id' => $folder->id,
        'price' => '40.00',
        'status' => ProductStatus::Draft->value,
        'drive_file_id' => '1AbC-dEf_123',
        'file_name' => 'COMBINATION_A155F.zip',
        'file_size' => 2147483648,
    ];
}

test('customers cannot open the product list', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(ProductResource::getUrl('index'))
        ->assertForbidden();
});

test('an admin creates a product', function () {
    actingAsAdmin();
    $folder = Folder::factory()->create();

    Livewire::test(CreateProduct::class)
        ->fillForm(validProductForm($folder))
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('products', [
        'title' => 'Galaxy A15 Combination File',
        'slug' => 'galaxy-a15-combination-file',
        'folder_id' => $folder->id,
        'price' => '40.00',
        'drive_file_id' => '1AbC-dEf_123',
        'file_size' => 2147483648,
    ]);
});

test('invalid product details are rejected', function (array $override, string $field) {
    actingAsAdmin();
    Product::factory()->create(['drive_file_id' => 'already-used']);

    Livewire::test(CreateProduct::class)
        ->fillForm([...validProductForm(Folder::factory()->create()), ...$override])
        ->call('create')
        ->assertHasFormErrors([$field]);

    expect(Product::query()->count())->toBe(1);
})->with([
    'title is required' => [['title' => null], 'title'],
    'price cannot be negative' => [['price' => '-1.00'], 'price'],
    'price has at most two decimals' => [['price' => '1.999'], 'price'],
    'Drive file ID has no spaces or symbols' => [['drive_file_id' => 'not a valid id!'], 'drive_file_id'],
    'Drive file ID is not used twice' => [['drive_file_id' => 'already-used'], 'drive_file_id'],
]);

test('publishing several products at once records when each was published', function () {
    actingAsAdmin();
    $this->freezeSecond();
    $products = Product::factory()->count(2)->create();

    Livewire::test(ListProducts::class)
        ->selectTableRecords($products->modelKeys())
        ->callAction(TestAction::make('publish')->table()->bulk());

    $products->each(function (Product $product): void {
        $product->refresh();
        expect($product->status)->toBe(ProductStatus::Published)
            ->and($product->published_at?->equalTo(now()))->toBeTrue();
    });
});

test('several products can be given a new price at once', function () {
    actingAsAdmin();
    $products = Product::factory()->count(2)->create();
    $untouched = Product::factory()->paid('5.00')->create();

    Livewire::test(ListProducts::class)
        ->selectTableRecords($products->modelKeys())
        ->callAction(TestAction::make('setPrice')->table()->bulk(), data: ['price' => '25.50']);

    expect($products->map(fn (Product $product) => $product->refresh()->price)->all())->toBe(['25.50', '25.50'])
        ->and($untouched->refresh()->price)->toBe('5.00');
});

test('several products can be moved to another folder at once', function () {
    actingAsAdmin();
    $products = Product::factory()->count(2)->create();
    $target = Folder::factory()->create();

    Livewire::test(ListProducts::class)
        ->selectTableRecords($products->modelKeys())
        ->callAction(TestAction::make('moveToFolder')->table()->bulk(), data: ['folder_id' => $target->id]);

    expect($products->map(fn (Product $product) => $product->refresh()->folder_id)->unique()->all())->toBe([$target->id]);
});
