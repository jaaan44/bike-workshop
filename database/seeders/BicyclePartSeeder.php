<?php

namespace Database\Seeders;

use App\Models\BicyclePart;
use App\Models\BicyclePartCategory;
use Illuminate\Database\Seeder;

class BicyclePartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Brakes' => [
                'Front Brake', 'Rear Brake', 'Brake Pads', 'Brake Cable',
                'Brake Rotor', 'Brake Lever', 'Hydraulic Brake System',
            ],
            'Wheels' => [
                'Front Wheel', 'Rear Wheel', 'Rim', 'Spokes', 'Hub',
                'Tire', 'Inner Tube', 'Tubeless System',
            ],
            'Drivetrain' => [
                'Chain', 'Crankset', 'Bottom Bracket', 'Cassette', 'Freewheel',
                'Front Derailleur', 'Rear Derailleur', 'Shifter', 'Pedals',
            ],
            'Steering' => [
                'Handlebar', 'Stem', 'Headset', 'Fork',
            ],
            'Suspension' => [
                'Front Suspension', 'Rear Suspension',
            ],
            'Frame' => [
                'Frame', 'Seatpost', 'Saddle',
            ],
            'E-Bike' => [
                'Battery', 'Motor', 'Controller', 'Display', 'Wiring', 'Lights',
            ],
            'General' => [
                'Bike Inspection', 'Tune-Up', 'Cleaning', 'Lubrication',
                'Assembly', 'Safety Check', 'Other',
            ],
        ];

        $categoryIndex = 0;

        foreach ($categories as $categoryName => $parts) {
            $category = BicyclePartCategory::updateOrCreate(
                ['name' => $categoryName],
                ['sort_order' => $categoryIndex]
            );

            foreach ($parts as $partIndex => $partName) {
                BicyclePart::updateOrCreate(
                    ['bicycle_part_category_id' => $category->id, 'name' => $partName],
                    ['sort_order' => $partIndex]
                );
            }

            $categoryIndex++;
        }
    }
}
