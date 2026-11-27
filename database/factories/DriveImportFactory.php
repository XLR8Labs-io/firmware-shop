<?php

namespace Database\Factories;

use App\Enums\DriveImportStatus;
use App\Models\DriveImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriveImport>
 */
class DriveImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'drive_folder_id' => fake()->regexify('[A-Za-z0-9_-]{33}'),
            'default_price' => '0.00',
            'status' => DriveImportStatus::Queued,
        ];
    }
}
