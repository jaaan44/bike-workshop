<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for manual testing (password for all: "password").
 *
 * These are convenient, publicly-documented, guessable credentials — never
 * safe to create on a real deployment. DatabaseSeeder only calls this
 * seeder when shouldRun() says so (see its own docblock); it is never
 * called unconditionally, so a plain `php artisan db:seed` against a
 * production-configured environment silently skips demo accounts instead
 * of creating them.
 */
class DemoAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Whether demo accounts should be created for the given environment
     * name (App::environment()'s value). A pure function, not a call to
     * app()/config() directly, so it can be unit-tested without mutating
     * global application state.
     */
    public static function shouldRun(string $environment): bool
    {
        return in_array($environment, ['local', 'testing'], true);
    }

    public function run(): void
    {
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
