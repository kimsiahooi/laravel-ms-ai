<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Settings\SettingsRegistry;
use Illuminate\Database\Seeder;

/**
 * Seeds each tenant's `settings` table with its schema-defined default values, so the
 * settings form reflects real stored data rather than phantom code defaults. Seeding
 * is additive/idempotent (SettingsCategory::seedDefaults uses firstOrCreate) — only
 * missing keys are inserted; stored/edited values are never touched. Called on tenant
 * provision (via TenantDatabaseSeeder) and re-runnable to backfill a newly added field
 * across every tenant: `php artisan tenants:seed`.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (app(SettingsRegistry::class)->all() as $category) {
            $category->seedDefaults();
        }
    }
}
