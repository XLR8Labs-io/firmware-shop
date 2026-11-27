<?php

namespace Database\Factories;

use App\Models\Reseller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reseller>
 */
class ResellerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'country' => fake()->country(),
            'whatsapp' => '+8801711'.fake()->numerify('######'),
            'telegram' => null,
            'payment_methods' => 'bKash, Nagad',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the agent is hidden and can't send balance.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Link the agent to an account that can send balance.
     */
    public function withAccount(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user ?? User::factory(),
        ]);
    }
}
