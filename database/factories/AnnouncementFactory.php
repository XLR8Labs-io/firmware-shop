<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'body' => fake()->paragraph(),
            'show_in_ticker' => false,
            'is_published' => true,
            'published_at' => now()->subHour(),
        ];
    }

    /**
     * Indicate that the announcement is still a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the announcement also scrolls in the ticker.
     */
    public function inTicker(): static
    {
        return $this->state(fn (array $attributes) => [
            'show_in_ticker' => true,
        ]);
    }
}
