<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\StockOnHandData;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Requests\Tenant\StockOnHandRequest;
use App\Models\Warehouse;
use App\Models\WarehouseReorderLevel;
use App\Services\StockService;

/**
 * Read-only lookups for the inventory dialogs. Returns JSON (a Data object
 * serializes to JSON when returned from a controller).
 */
class StockLookupController
{
    use BuildsStockPickers;

    /**
     * On-hand + unit + reorder level for a (warehouse, item) pair, so the stock
     * movement / transfer dialogs can show the live figure beside the quantity.
     */
    public function onHand(StockOnHandRequest $request, StockService $service): StockOnHandData
    {
        $warehouse = Warehouse::findOrFail($request->integer('warehouse_id'));
        $stockable = $this->resolveStockable((string) $request->input('stockable'));

        $reorder = WarehouseReorderLevel::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('stockable_type', $stockable->getMorphClass())
            ->where('stockable_id', $stockable->getKey())
            ->value('min_stock');

        return new StockOnHandData(
            on_hand: $service->onHand($warehouse, $stockable),
            unit: $stockable->unit,
            reorder_level: $reorder !== null ? (float) $reorder : null,
        );
    }
}
