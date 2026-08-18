<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Demo accounts for manual testing (password for all: "password").
        User::factory()->create([
            'name' => 'Casey Customer',
            'email' => 'customer@example.com',
        ]);

        User::factory()->staff()->create([
            'name' => 'Sam Staff',
            'email' => 'staff@example.com',
        ]);

        User::factory()->technician()->create([
            'name' => 'Tony Technician',
            'email' => 'technician@example.com',
        ]);
    }
}
