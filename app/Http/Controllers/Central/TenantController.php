<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Actions\ProvisionTenant;
use App\Http\Requests\Central\StoreTenantRequest;
use App\Models\Tenant;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantController
{
    /** @var array<int, int> */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function index(Request $request): Response
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

        return Inertia::render('admin/tenants/index', [
            'tenants' => $tenants,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(StoreTenantRequest $request, ProvisionTenant $provision): RedirectResponse
    {
        $data = $request->validated();

        $tenant = $provision->handle(
            name: $data['name'],
            slug: $data['slug'],
            adminName: $data['admin_name'],
            adminEmail: $data['admin_email'],
            adminPassword: $data['admin_password'],
        );

        return redirect()->route('admin.tenants.index')
            ->with('success', "Tenant \"{$tenant->name}\" created — login at /{$tenant->getKey()}/login.");
    }

    public function trashed(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = (int) $request->integer('per_page', 10);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 10;
        }

        $tenants = Tenant::onlyTrashed()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $group) use ($search): void {
                    $group->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Tenant $tenant): array => [
                'slug' => $tenant->getKey(),
                'name' => $tenant->name,
                'deleted_at' => $tenant->deleted_at,
            ]);

        return Inertia::render('admin/tenants/trashed', [
            'tenants' => $tenants,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return back()->with('success', "Tenant \"{$tenant->name}\" moved to the archive.");
    }

    public function restore(Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->trashed(), 404);

        $tenant->restore();

        return back()->with('success', "Tenant \"{$tenant->name}\" restored.");
    }

    public function forceDestroy(Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->trashed(), 404);

        $name = $tenant->name;
        $tenant->forceDelete();

        return back()->with('success', "Tenant \"{$name}\" permanently deleted.");
    }
}
