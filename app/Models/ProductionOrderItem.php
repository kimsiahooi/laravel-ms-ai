<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One exploded BOM line of a production order. `quantity_required` is what
 * "Complete" consumes; `raw_material_snapshot` is the name/sku/unit captured at
 * write time. Lives on the default (tenant) connection.
 *
 * @property int $id
 * @property int $production_order_id
 * @property int|null $raw_material_id
 * @property array{name: string, sku: string, unit: string} $raw_material_snapshot
 * @property string $quantity_per_unit
 * @property string $quantity_required
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ProductionOrder $productionOrder
 * @property-read RawMaterial|null $rawMaterial
 */
#[Fillable(['production_order_id', 'raw_material_id', 'raw_material_snapshot', 'quantity_per_unit', 'quantity_required'])]
class ProductionOrderItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_material_snapshot' => 'array',
            'quantity_per_unit' => 'decimal:4',
            'quantity_required' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<ProductionOrder, $this>
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    /**
     * @return BelongsTo<RawMaterial, $this>
     */
    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
