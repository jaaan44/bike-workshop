<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\TechnicianNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnicianNote>
 */
class TechnicianNoteFactory extends Factory
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
            'user_id' => User::factory(),
            'note' => fake()->sentence(),
        ];
    }
}
