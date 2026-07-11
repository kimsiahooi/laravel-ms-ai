<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\OptionData;
use App\Data\WarehouseData;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\WarehouseRequest;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController
{
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = $this->perPage($request);

        $warehouses = Warehouse::query()
            ->with('location')
            ->search($search)
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Warehouse $warehouse): WarehouseData => WarehouseData::from($warehouse));

        return Inertia::render('tenant/warehouses/index', [
            'warehouses' => $warehouses,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'locations' => OptionData::collect(Location::orderBy('name')->get(['id', 'name'])),
        ]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        Warehouse::create($request->validated());

        $this->toast('Warehouse created.');

        return back();
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        $this->toast('Warehouse updated.');

        return back();
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $warehouse->delete();

        $this->toast('Warehouse deleted.');

        return back();
    }
}
