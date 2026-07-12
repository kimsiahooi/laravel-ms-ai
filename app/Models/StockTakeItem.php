<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One counted line of a stock take. `stockable_snapshot` is the name/sku/unit
 * captured at count time. Lives on the default (tenant) connection.
 *
 * @property int $id
 * @property int $stock_take_id
 * @property string $stockable_type
 * @property int $stockable_id
 * @property array{name: string, sku: string|null, unit: string} $stockable_snapshot
 * @property string $system_qty
 * @property string $counted_qty
 * @property string $variance
 * @property-read StockTake $stockTake
 * @property-read Model $stockable
 */
#[Fillable([
    'stock_take_id', 'stockable_type', 'stockable_id',
    'stockable_snapshot', 'system_qty', 'counted_qty', 'variance',
])]
class StockTakeItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stockable_snapshot' => 'array',
            'system_qty' => 'decimal:4',
            'counted_qty' => 'decimal:4',
            'variance' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<StockTake, $this>
     */
    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function stockable(): MorphTo
    {
        return $this->morphTo('stockable');
    }
}
