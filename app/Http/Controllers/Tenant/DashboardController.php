<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /**
     * The tenant dashboard currently surfaces only the signed-in user (the
     * globally-shared `auth.user` prop) and the current organisation. The
     * analytics KPIs/charts were removed and will be reintroduced later.
     */
    public function __invoke(): Response
    {
        $tenant = tenant();

        return Inertia::render('tenant/dashboard', [
            'organization' => [
                'name' => $tenant->name,
                'slug' => $tenant->getKey(),
                'logo' => $tenant->logo,
                'created_at' => $tenant->created_at?->toIso8601String(),
                'members' => User::count(),
            ],
        ]);
    }
}
