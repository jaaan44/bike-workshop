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
        $this->call(BicycleTypeSeeder::class);

        // Demo accounts for manual testing (password for all: "password").
        // Checking existence first (rather than firstOrCreate with a factory's
        // raw() attributes) avoids the factory's own random default email
        // silently overwriting the one we're searching/creating by.
        if (! User::where('email', 'customer@example.com')->exists()) {
            User::factory()->create(['name' => 'Casey Customer', 'email' => 'customer@example.com']);
        }

        if (! User::where('email', 'staff@example.com')->exists()) {
            User::factory()->staff()->create(['name' => 'Sam Staff', 'email' => 'staff@example.com']);
        }

        if (! User::where('email', 'technician@example.com')->exists()) {
            User::factory()->technician()->create(['name' => 'Tony Technician', 'email' => 'technician@example.com']);
        }
    }
}
