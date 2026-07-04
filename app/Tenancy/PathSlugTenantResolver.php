<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Routing\Route;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByPathException;
use Stancl\Tenancy\Resolvers\PathTenantResolver;

/**
 * Resolves the {tenant} path segment by the tenant's `slug` column instead of
 * the default tenant key/id, so URLs read `/{slug}/…` while the tenant key stays
 * a stable integer id (renaming a slug never orphans a database).
 *
 * Only resolveWithoutCache() is overridden — resolve() lives in the parent
 * CachedTenantResolver and delegates here, preserving all caching behaviour.
 */
class PathSlugTenantResolver extends PathTenantResolver
{
    public function resolveWithoutCache(...$args): Tenant
    {
        /** @var Route $route */
        $route = $args[0];

        // Explicit null/empty check — NOT truthiness: a valid unique slug can be
        // the string "0", which is falsy in PHP and would otherwise be skipped.
        $slug = $route->parameter(static::$tenantParameterName);

        if ($slug !== null && $slug !== '') {
            // Never leak the {tenant} segment into controller arguments.
            $route->forgetParameter(static::$tenantParameterName);

            if ($tenant = tenancy()->query()->where('slug', $slug)->first()) {
                return $tenant;
            }
        }

        throw new TenantCouldNotBeIdentifiedByPathException($slug);
    }

    /**
     * Cache-key args must mirror what we resolve by (the slug) rather than the
     * parent's id, so cache invalidation targets the right key.
     *
     * @return array<int, array<int, mixed>>
     */
    public function getArgsForTenant(Tenant $tenant): array
    {
        return [
            [$tenant->slug],
        ];
    }
}
