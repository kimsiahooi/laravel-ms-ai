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
            'signups' => $this->signups(),
        ]);
    }

    /**
     * New-tenant count per day for the trailing 30 days (zero-filled, oldest
     * first). Bucketed in PHP so it behaves the same on MySQL and SQLite.
     *
     * @return array<int, array{date: string, label: string, count: int}>
     */
    private function signups(): array
    {
        $byDay = Tenant::query()
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->get(['created_at'])
            ->groupBy(fn (Tenant $tenant): string => $tenant->created_at->format('Y-m-d'));

        $out = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $out[] = [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('M j'),
                'count' => $byDay->get($day->format('Y-m-d'), collect())->count(),
            ];
        }

        return $out;
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
