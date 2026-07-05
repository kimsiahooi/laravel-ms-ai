<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Models\Tenant;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /** @var array<int, int> */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function __invoke(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = (int) $request->integer('per_page', 10);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 10;
        }

        $tenants = Tenant::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $group) use ($search): void {
                    $group->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Tenant $tenant): array => [
                'slug' => $tenant->getKey(),
                'name' => $tenant->name,
                'created_at' => $tenant->created_at,
            ]);

        return Inertia::render('admin/dashboard', [
            'tenants' => $tenants,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            // A closure so the stat counts run only on a full page load — the
            // partial reloads used for search / pagination don't request `stats`,
            // so Inertia keeps the previously-loaded values without re-querying.
            'stats' => fn (): array => $this->stats(),
        ]);
    }

    /**
     * Aggregate stats over ALL tenants (independent of the current page/search).
     * Counts use the app timezone (UTC).
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
