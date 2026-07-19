<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The root seeder for a TENANT database — the single entry point for baseline data
 * every tenant should have (currently the default settings). Runs on tenant provision
 * (App\Actions\ProvisionTenant) and via `php artisan tenants:seed`
 * (config/tenancy.php → seeder_parameters points here). All sub-seeders are additive,
 * so it's safe to re-run to sync new baseline data across existing tenants.
 *
 * NOT the central DatabaseSeeder (which seeds the super-admin), and NOT DemoTenantSeeder
 * (opt-in sample data, seeded separately by ProvisionTenant when requested).
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
        ]);
    }
}
