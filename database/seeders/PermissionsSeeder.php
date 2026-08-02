<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\TenantPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the fixed permission catalog into a tenant DB (guard `web`). Idempotent
 * and additive — re-running adds any newly-defined permissions to existing tenants.
 */
class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (TenantPermissions::names() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
