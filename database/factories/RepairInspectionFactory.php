<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\RepairInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairInspection>
 */
class RepairInspectionFactory extends Factory
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
            'findings' => fake()->sentence(),
            'recommended_repairs' => fake()->sentence(),
        ];
    }
}
