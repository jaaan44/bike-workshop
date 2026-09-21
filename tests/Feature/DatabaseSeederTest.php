<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Confirms DatabaseSeeder is actually wired to DemoAccountSeeder's
 * environment guard end-to-end (see DemoAccountSeederTest for the guard's
 * own logic in isolation). The test suite always runs with
 * APP_ENV=testing, which DemoAccountSeeder::shouldRun() allows, so this
 * exercises the "local/testing stays convenient" half of the fix; the
 * "production is guarded" half is covered directly (and without brittle
 * environment manipulation) by DemoAccountSeederTest.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_in_the_testing_environment_still_creates_demo_accounts(): void
    {
        Artisan::call('db:seed');

        $this->assertTrue(User::where('email', 'customer@example.com')->exists());
        $this->assertTrue(User::where('email', 'staff@example.com')->exists());
        $this->assertTrue(User::where('email', 'technician@example.com')->exists());
    }

    public function test_seeding_is_safe_to_run_twice(): void
    {
        Artisan::call('db:seed');
        Artisan::call('db:seed');

        $this->assertSame(1, User::where('email', 'staff@example.com')->count());
    }
}
