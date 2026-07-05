<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Support\ReservedSlugs;
use Illuminate\Validation\ValidationException;

class ProvisionTenant
{
    /**
     * Create a tenant (the TenantCreated pipeline creates + migrates its database
     * synchronously), then seed the first tenant user inside the tenant database.
     * Reserved slugs are rejected before any database work.
     */
    public function handle(
        string $name,
        string $slug,
        string $adminName,
        string $adminEmail,
        string $adminPassword,
    ): Tenant {
        if (in_array($slug, ReservedSlugs::LIST, true)) {
            throw ValidationException::withMessages([
                'slug' => "The slug \"{$slug}\" is reserved and cannot be used.",
            ]);
        }

        // Central connection. Creating the tenant fires TenantCreated ->
        // CreateDatabase + MigrateDatabase (synchronous) before this returns.
        $tenant = Tenant::create([
            'name' => $name,
            'id' => $slug, // the id column stores the slug (the tenant key)
        ]);

        // run() switches the default connection to the tenant DB, executes the
        // closure, then reverts. User::create writes to the tenant users table;
        // the password is hashed by the model's 'hashed' cast (do not pre-hash).
        $tenant->run(function () use ($adminName, $adminEmail, $adminPassword): void {
            User::create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $adminPassword,
            ]);
        });

        return $tenant;
    }
}
