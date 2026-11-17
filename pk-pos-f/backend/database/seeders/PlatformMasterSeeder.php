<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PlatformMasterSeeder extends Seeder
{
    /**
     * Seed baseline platform master & configuration data.
     * Safe to run in production: completely idempotent, creates no dummy stores or fake transactions.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            PlanSeeder::class,
            MasterDataSeeder::class,
            ReleaseSeeder::class,
            SupportSeeder::class,
            SystemSettingSeeder::class,
        ]);
    }
}
