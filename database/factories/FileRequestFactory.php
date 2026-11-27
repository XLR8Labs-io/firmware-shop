<?php

namespace Database\Factories;

use App\Enums\FileRequestStatus;
use App\Models\FileRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileRequest>
 */
class FileRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'contact' => fake()->safeEmail(),
            'brand' => fake()->randomElement(['Samsung', 'Xiaomi', 'Oppo', 'Vivo', 'Realme']),
            'model' => strtoupper(fake()->bothify('??####')),
            'details' => fake()->sentence(),
            'status' => FileRequestStatus::Open,
        ];
    }
}
