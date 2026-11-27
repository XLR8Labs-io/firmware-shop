<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Folder;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileName = fake()->unique()->bothify('SM-A###F_U#_????_Firmware').'.zip';

        return [
            'folder_id' => Folder::factory(),
            'title' => pathinfo($fileName, PATHINFO_FILENAME),
            'model_names' => null,
            'price' => '0.00',
            'is_featured' => false,
            'status' => ProductStatus::Draft,
            'drive_file_id' => fake()->unique()->regexify('[A-Za-z0-9_-]{33}'),
            'file_name' => $fileName,
            'file_size' => fake()->numberBetween(5_000_000, 8_000_000_000),
            'md5' => fake()->md5(),
            'mime_type' => 'application/zip',
        ];
    }

    /**
     * Indicate that the product is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 60)),
        ]);
    }

    /**
     * Indicate that the product was published and then taken down.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Hidden,
            'published_at' => now()->subDays(30),
        ]);
    }

    /**
     * Give the product a price.
     */
    public function paid(string $price = '10.00'): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
        ]);
    }

    /**
     * Indicate that the product is featured on the home page.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
