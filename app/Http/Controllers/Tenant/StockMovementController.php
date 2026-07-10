<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\OptionData;
use App\Data\StockMovementData;
use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\StockMovementRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController
{
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $perPage = $this->perPage($request);

        $movements = StockMovement::query()
            ->with(['location.warehouse', 'stockable', 'user'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (StockMovement $movement): StockMovementData => StockMovementData::from($movement));

        // Locations for the picker, labelled "Warehouse · code".
        $locations = OptionData::collect(
            Location::with('warehouse')
                ->orderBy('code')
                ->get()
                ->map(fn (Location $location): array => [
                    'id' => $location->id,
                    'name' => ($location->warehouse?->name ?? '?').' · '.$location->code,
                ]),
        );

        // One merged item picker: products + raw materials, value "product:5" / "raw_material:3".
        $items = Product::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Product $product): array => [
                'value' => 'product:'.$product->id,
                'label' => $product->name.' · Product',
            ])
            ->concat(
                RawMaterial::orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (RawMaterial $rawMaterial): array => [
                        'value' => 'raw_material:'.$rawMaterial->id,
                        'label' => $rawMaterial->name.' · Raw material',
                    ]),
            )
            ->values()
            ->all();

        return Inertia::render('tenant/stock-movements/index', [
            'movements' => $movements,
            'filters' => [
                'search' => '',
                'per_page' => $perPage,
            ],
            'locations' => $locations,
            'items' => $items,
        ]);
    }

    public function store(StockMovementRequest $request, StockService $service): RedirectResponse
    {
        [$type, $id] = explode(':', (string) $request->input('stockable'), 2);

        $stockable = $type === 'product'
            ? Product::findOrFail($id)
            : RawMaterial::findOrFail($id);

        $location = Location::findOrFail($request->integer('location_id'));
        $user = $request->user();
        $quantity = (float) $request->input('quantity');
        $notes = $request->input('notes');

        try {
            match ($request->input('type')) {
                'in' => $service->record($location, $stockable, $quantity, StockMovementReason::Adjustment, $user, $notes),
                'out' => $service->record($location, $stockable, -$quantity, StockMovementReason::Adjustment, $user, $notes),
                'adjustment' => $service->setLevel($location, $stockable, $quantity, $user, $notes),
            };
        } catch (InsufficientStockException) {
            throw ValidationException::withMessages([
                'quantity' => 'Not enough stock at this location.',
            ]);
        }

        $this->toast('Movement recorded.');

        return back();
    }
}
