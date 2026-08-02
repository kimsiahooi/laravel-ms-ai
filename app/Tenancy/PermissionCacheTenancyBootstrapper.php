<?php

declare(strict_types=1);

namespace App\Tenancy;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Scopes spatie/laravel-permission's cache per tenant.
 *
 * The package resolves its cache store directly (bypassing stancl's tag-based
 * CacheManager) and caches every tenant's roles/permissions under one fixed key.
 * With a shared cache store (redis/memcached) that key would be reused across
 * tenants — a cross-tenant authorization leak. Making the key tenant-specific
 * isolates it under ANY driver (today's `database` store already isolates via each
 * tenant's own cache table, but this stops a driver switch from silently breaking
 * it). Clearing the in-memory collection on switch also stops a long-running
 * process (queue worker, Octane, tests) carrying one tenant's permissions into the
 * next request.
 *
 * clearPermissionsCollection() resets only the in-memory collection; it does NOT
 * forget the store, so each tenant keeps benefiting from its own cached permissions.
 */
final class PermissionCacheTenancyBootstrapper implements TenancyBootstrapper
{
    private ?string $originalKey = null;

    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->originalKey ??= $this->registrar->cacheKey;

        $this->registrar->cacheKey = $this->originalKey.'.tenant.'.$tenant->getTenantKey();
        $this->registrar->clearPermissionsCollection();
    }

    public function revert(): void
    {
        if ($this->originalKey !== null) {
            $this->registrar->cacheKey = $this->originalKey;
        }

        $this->registrar->clearPermissionsCollection();
    }
}
