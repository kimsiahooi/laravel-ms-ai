<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Settings\BusinessSettings;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenant = tenant();

        // The signed-in tenant user (web guard). On /admin pages this is null (that
        // area authenticates a CentralUser on the 'central' guard instead), so
        // permissions/is_admin below stay empty there.
        $webUser = $request->user();

        // Tenant permissions live in the tenant database and only apply inside a
        // workspace, so only resolve them there — a central/guest page has none,
        // and its DB has no permission tables to query.
        $tenantUser = $tenant && $webUser instanceof User ? $webUser : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // Resolve the correct guard per area: the /admin pages authenticate
                // super-admins on the 'central' guard, everything else the 'web' guard.
                'user' => $request->is('admin', 'admin/*')
                    ? $request->user('central')
                    : $webUser,
                // The tenant user's permissions + whether they're the built-in
                // Administrator, so the UI can hide what they can't do. Enforcement
                // still lives server-side (AuthorizeTenantRoute).
                'permissions' => $tenantUser?->getAllPermissions()->pluck('name')->all() ?? [],
                'is_admin' => $tenantUser !== null && $tenantUser->hasRole(RolesSeeder::ADMIN_ROLE),
            ],
            'tenant' => $tenant ? [
                'slug' => $tenant->getKey(),
                'name' => $tenant->name,
            ] : null,
            // The company profile for document headers — shared read-only, only in
            // tenant context for an authed user (so guests/central pages never query
            // the tenant DB). Reads are side-effect free; empty fields fall back to
            // tenant.name on the document.
            'business' => $tenant && $request->user()
                ? fn () => app(BusinessSettings::class)->documentHeader()
                : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
