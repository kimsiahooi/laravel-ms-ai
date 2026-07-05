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
            'stats' => $this->stats(),
        ]);
    }

    /**
     * Aggregate stats over ALL (non-trashed) tenants. Counts use the app
     * timezone (UTC).
     *
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $newest = Tenant::query()->latest()->first(['name', 'created_at']);

        return [
            'total' => Tenant::query()->count(),
            'added_today' => Tenant::query()->whereDate('created_at', today())->count(),
            'last_7_days' => Tenant::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'newest' => $newest ? [
                'name' => $newest->name,
                'created_at' => $newest->created_at,
            ] : null,
        ];
    }
}
