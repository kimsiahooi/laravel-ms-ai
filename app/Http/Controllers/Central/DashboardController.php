<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            // The `id` column stores the slug; expose it to the frontend as `slug`.
            'tenants' => Tenant::query()
                ->latest()
                ->get(['id', 'name', 'created_at'])
                ->map(fn (Tenant $tenant): array => [
                    'slug' => $tenant->getKey(),
                    'name' => $tenant->name,
                    'created_at' => $tenant->created_at,
                ]),
        ]);
    }
}
