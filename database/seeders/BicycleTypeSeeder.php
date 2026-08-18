<?php

namespace Database\Seeders;

use App\Models\BicycleType;
use Illuminate\Database\Seeder;

class BicycleTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'Road Bike',
            'Mountain Bike',
            'Gravel Bike',
            'Folding Bike',
            'BMX',
            'City Bike',
            'Hybrid Bike',
            'E-Bike',
            'Kids Bike',
            'Other',
        ];

        foreach ($types as $index => $name) {
            BicycleType::updateOrCreate(['name' => $name], ['sort_order' => $index]);
        }
    }
}
