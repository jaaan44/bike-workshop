<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * BicycleTypeSeeder/BicyclePartSeeder are lookup data the app needs in
     * every environment, so they always run. DemoAccountSeeder creates
     * accounts with a publicly-documented, guessable password ("password")
     * and is only ever called when DemoAccountSeeder::shouldRun() says the
     * current environment is safe for it (local/testing) — never
     * unconditionally, so a plain `php artisan db:seed` against a
     * production-configured environment can't silently create predictable
     * privileged credentials (Phase 10A finding). See README.md's "Demo
     * accounts" section for the operator-facing explanation.
     */
    public function run(): void
    {
        $this->call(BicycleTypeSeeder::class);
        $this->call(BicyclePartSeeder::class);

        if (DemoAccountSeeder::shouldRun(app()->environment())) {
            $this->call(DemoAccountSeeder::class);
        }
    }
}
