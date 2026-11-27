<?php

use App\Models\Folder;
use App\Models\Product;
use App\Models\User;

test('a product page shows the file details and never the Drive file ID', function () {
    $samsung = Folder::factory()->create(['name' => 'Samsung']);
    $product = Product::factory()->published()->paid('40.00')->for($samsung)->create([
        'title' => 'Galaxy A15 Combination',
        'file_name' => 'COMBINATION_A155F.zip',
        'file_size' => 2147483648,
        'md5' => '0cc175b9c0f1b6a831c399e269772661',
        'drive_file_id' => '1SecretDriveFileId',
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSeeInOrder(['Home', 'Samsung', 'Galaxy A15 Combination'])
        ->assertSee('COMBINATION_A155F.zip')
        ->assertSee('2.00 GB')
        ->assertSee('0cc175b9c0f1b6a831c399e269772661')
        ->assertSee('$40.00')
        ->assertSee('Log in to buy')
        ->assertDontSee('1SecretDriveFileId');
});

test('products customers may not see are not found', function (Closure $makeProduct) {
    $this->get(route('products.show', $makeProduct()))->assertNotFound();
})->with([
    'draft' => [fn () => Product::factory()->create()],
    'taken down' => [fn () => Product::factory()->hidden()->create()],
    'in a hidden folder' => [fn () => Product::factory()->published()->for(Folder::factory()->hidden())->create()],
]);

test('a deleted product is not found', function () {
    $product = Product::factory()->published()->create();
    $slug = $product->slug;
    $product->delete();

    $this->get("/files/{$slug}")->assertNotFound();
});

test('the description is rendered as Markdown without running scripts or unsafe links', function () {
    $product = Product::factory()->published()->create([
        'description' => "## Steps\n\nFlash with **Odin**.\n\n<script>alert('x')</script>\n\n[Click me](javascript:alert(1))",
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('<h2>Steps</h2>', escape: false)
        ->assertSee('<strong>Odin</strong>', escape: false)
        ->assertDontSee("<script>alert('x')</script>", escape: false)
        ->assertDontSee('href="javascript:', escape: false);
});

test('the file password is shown to signed-in users for free files, and to nobody else without buying', function () {
    $free = Product::factory()->published()->create(['file_password' => 'free-pass-123']);
    $paid = Product::factory()->published()->paid()->create(['file_password' => 'paid-pass-456']);
    $customer = User::factory()->create();

    $this->get(route('products.show', $free))->assertDontSee('free-pass-123');
    $this->actingAs($customer)->get(route('products.show', $free))->assertSee('free-pass-123');
    $this->actingAs($customer)->get(route('products.show', $paid))->assertDontSee('paid-pass-456');
});
