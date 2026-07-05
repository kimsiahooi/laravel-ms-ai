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
            'tenants' => Tenant::query()
                ->latest()
                ->get(['id', 'name', 'slug', 'created_at']),
        ]);
    }
}
