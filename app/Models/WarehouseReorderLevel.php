<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Per-(warehouse, stockable) reorder threshold. Lives on the default connection,
 * which InitializeTenancyByPath has switched to the tenant database.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property string $stockable_type
 * @property int $stockable_id
 * @property string $min_stock
 * @property-read Warehouse $warehouse
 * @property-read Model $stockable
 */
#[Fillable(['warehouse_id', 'stockable_type', 'stockable_id', 'min_stock'])]
class WarehouseReorderLevel extends Model
{
    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_stock' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function stockable(): MorphTo
    {
        return $this->morphTo('stockable');
    }

    /**
     * The single source of truth for "low stock": rows whose on-hand quantity in the
     * same warehouse is at or below a set reorder level (`<=` matches the UI's
     * stockStatus() and the "drops to this level" copy), for a live warehouse. Joins
     * `warehouses as w` + `warehouse_stocks as ws`, so callers can select from those
     * aliases (e.g. `COALESCE(ws.quantity, 0)`).
     *
     * @param  Builder<WarehouseReorderLevel>  $query
     */
    public function scopeBelowLevel($query): void
    {
        $query
            ->join('warehouses as w', 'w.id', '=', 'warehouse_reorder_levels.warehouse_id')
            ->leftJoin('warehouse_stocks as ws', function ($join) {
                $join->on('ws.warehouse_id', '=', 'warehouse_reorder_levels.warehouse_id')
                    ->on('ws.stockable_type', '=', 'warehouse_reorder_levels.stockable_type')
                    ->on('ws.stockable_id', '=', 'warehouse_reorder_levels.stockable_id');
            })
            ->whereNull('w.deleted_at')
            ->where('warehouse_reorder_levels.min_stock', '>', 0)
            ->whereRaw('COALESCE(ws.quantity, 0) <= warehouse_reorder_levels.min_stock');
    }
}
