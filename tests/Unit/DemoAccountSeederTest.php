<?php

namespace Tests\Unit;

use Database\Seeders\DemoAccountSeeder;
use Tests\TestCase;

/**
 * Phase 10A finding: `php artisan db:seed` unconditionally created demo
 * accounts (including staff@example.com / "password", a full-access
 * workshop account) with no guard against running it on a real deployment.
 * DemoAccountSeeder::shouldRun() is a pure function of the environment name
 * — tested directly with literal strings here, not by mutating the actual
 * application environment (app()->environment()/config()), which would be
 * a brittle way to exercise the same logic.
 */
class DemoAccountSeederTest extends TestCase
{
    public function test_should_run_in_local_and_testing_environments(): void
    {
        $this->assertTrue(DemoAccountSeeder::shouldRun('local'));
        $this->assertTrue(DemoAccountSeeder::shouldRun('testing'));
    }

    public function test_should_not_run_in_production_or_any_other_environment(): void
    {
        $this->assertFalse(DemoAccountSeeder::shouldRun('production'));
        $this->assertFalse(DemoAccountSeeder::shouldRun('staging'));
        $this->assertFalse(DemoAccountSeeder::shouldRun(''));
    }
}
