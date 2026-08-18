<?php

namespace Database\Factories;

use App\Models\Bicycle;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bicycle_id' => Bicycle::factory(),
            'remarks' => fake()->sentence(),
            'appointment_date' => now()->addDays(3)->toDateString(),
        ];
    }
}
