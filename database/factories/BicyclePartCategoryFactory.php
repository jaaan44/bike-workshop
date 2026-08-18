<?php

namespace Database\Factories;

use App\Models\BicyclePartCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BicyclePartCategory>
 */
class BicyclePartCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
