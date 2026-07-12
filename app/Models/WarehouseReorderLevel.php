<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
}
