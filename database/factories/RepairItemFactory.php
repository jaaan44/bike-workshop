<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\RepairItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairItem>
 */
class RepairItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'description' => fake()->sentence(4),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }
}
