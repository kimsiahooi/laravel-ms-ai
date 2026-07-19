<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\BusinessSettings;
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

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // Resolve the correct guard per area: the /admin pages authenticate
                // super-admins on the 'central' guard, everything else the 'web' guard.
                'user' => $request->is('admin', 'admin/*')
                    ? $request->user('central')
                    : $request->user(),
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
