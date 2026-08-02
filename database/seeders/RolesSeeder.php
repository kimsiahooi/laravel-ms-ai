<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\TenantPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the built-in "Administrator" role into a tenant DB. It always holds every
 * permission (re-synced here so new permissions are picked up) and is locked in
 * the UI — it can't be edited or deleted, so a tenant can never lock itself out.
 * Runs after {@see PermissionsSeeder}.
 */
class RolesSeeder extends Seeder
{
    public const ADMIN_ROLE = 'Administrator';

    public function run(): void
    {
        $admin = Role::findOrCreate(self::ADMIN_ROLE, 'web');
        $admin->syncPermissions(TenantPermissions::names());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
