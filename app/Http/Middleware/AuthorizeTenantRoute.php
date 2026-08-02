<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TenantPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the tenant permission catalog. Maps the current route to the permission
 * it requires ({@see TenantPermissions::routeMap()}) and 403s a signed-in user who
 * lacks it. Routes with no mapped permission (dashboard, media, on-hand lookup,
 * logout) stay open to any signed-in user. Applied to the tenant `auth:web` group,
 * so a user is always present.
 */
class AuthorizeTenantRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $permission = $this->permissionFor($request);

        abort_if(
            $permission !== null && $request->user()?->can($permission) !== true,
            403,
        );

        return $next($request);
    }

    /** The permission the current route requires, or null if it's open. */
    private function permissionFor(Request $request): ?string
    {
        $name = $request->route()?->getName();
        if ($name === null || ! str_starts_with($name, 'tenant.')) {
            return null;
        }
        $name = substr($name, strlen('tenant.'));

        // Export downloads a resource's data, so gate it on that resource's view —
        // but only for a known resource. An unknown one has no such permission, so
        // let it fall through to the controller (a 404), not a 403.
        if ($name === 'export') {
            $permission = (string) $request->route('resource').'.view';

            return in_array($permission, TenantPermissions::names(), true)
                ? $permission
                : null;
        }

        return TenantPermissions::routeMap()[$name] ?? null;
    }
}
