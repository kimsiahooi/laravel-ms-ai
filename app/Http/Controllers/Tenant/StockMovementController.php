<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\StockMovementData;
use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\StockMovementRequest;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController
{
    use BuildsStockPickers;
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        $movements = StockMovement::query()
            ->with(['warehouse.location', 'stockable', 'user'])
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (StockMovement $movement): StockMovementData => StockMovementData::from($movement));

        return Inertia::render('tenant/stock-movements/index', [
            'movements' => $movements,
            'warehouses' => $this->stockWarehouseOptions(),
            'items' => $this->stockItemOptions(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Filter the ledger by item name/sku (product or raw material), reason,
     * notes, or the movement's warehouse code / warehouse name.
     *
     * @param  Builder<StockMovement>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';

        $query->where(function (Builder $group) use ($like): void {
            $group
                ->where('notes', 'like', $like)
                ->orWhere('reason', 'like', $like)
                ->orWhereHasMorph(
                    'stockable',
                    [Product::class, RawMaterial::class],
                    fn (Builder $item) => $item->where('name', 'like', $like)->orWhere('sku', 'like', $like),
                )
                ->orWhereHas('warehouse', fn (Builder $warehouse) => $warehouse
                    ->where('code', 'like', $like)
                    ->orWhereHas('location', fn (Builder $location) => $location->where('name', 'like', $like)));
        });
    }

    public function store(StockMovementRequest $request, StockService $service): RedirectResponse
    {
        $stockable = $this->resolveStockable((string) $request->input('stockable'));

        $warehouse = Warehouse::findOrFail($request->integer('warehouse_id'));
        $user = $request->user();
        $quantity = (float) $request->input('quantity');
        $notes = $request->input('notes');

        try {
            match ($request->input('type')) {
                'in' => $service->record($warehouse, $stockable, $quantity, StockMovementReason::Adjustment, $user, $notes),
                'out' => $service->record($warehouse, $stockable, -$quantity, StockMovementReason::Adjustment, $user, $notes),
                'adjustment' => $service->setLevel($warehouse, $stockable, $quantity, $user, $notes),
            };
        } catch (InsufficientStockException) {
            throw ValidationException::withMessages([
                'quantity' => 'Not enough stock at this warehouse.',
            ]);
        }

        $this->toast('Movement recorded.');

        return back();
    }
}
