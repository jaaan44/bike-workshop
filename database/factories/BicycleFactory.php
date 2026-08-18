<?php

namespace Database\Factories;

use App\Models\Bicycle;
use App\Models\BicycleType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bicycle>
 */
class BicycleFactory extends Factory
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
            'bicycle_type_id' => BicycleType::factory(),
            'nickname' => fake()->randomElement(['My Road Bike', 'Daily Commuter', 'Trail Bike']),
            'brand' => fake()->company(),
            'model' => fake()->word(),
        ];
    }
}
