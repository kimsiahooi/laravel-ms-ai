<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Requests\Tenant\RawMaterialRequest;
use App\Models\RawMaterial;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RawMaterialController
{
    use ResolvesPerPage;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = $this->perPage($request);

        $rawMaterials = RawMaterial::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $group) use ($search): void {
                    $group->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (RawMaterial $rawMaterial): array => [
                'id' => $rawMaterial->id,
                'name' => $rawMaterial->name,
                'sku' => $rawMaterial->sku,
                'unit' => $rawMaterial->unit,
                'min_stock' => $rawMaterial->min_stock,
                'created_at' => $rawMaterial->created_at,
            ]);

        return Inertia::render('tenant/raw-materials/index', [
            'rawMaterials' => $rawMaterials,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(RawMaterialRequest $request): RedirectResponse
    {
        RawMaterial::create($request->validated());

        return back()->with('success', 'Raw material created.');
    }

    public function update(RawMaterialRequest $request, RawMaterial $rawMaterial): RedirectResponse
    {
        $rawMaterial->update($request->validated());

        return back()->with('success', 'Raw material updated.');
    }

    public function destroy(RawMaterial $rawMaterial): RedirectResponse
    {
        $rawMaterial->delete();

        return back()->with('success', 'Raw material deleted.');
    }
}
