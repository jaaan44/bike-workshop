<?php

namespace Database\Factories;

use App\Models\BicyclePart;
use App\Models\BicyclePartCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BicyclePart>
 */
class BicyclePartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bicycle_part_category_id' => BicyclePartCategory::factory(),
            'name' => fake()->unique()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
