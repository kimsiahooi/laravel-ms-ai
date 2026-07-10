<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a purchase order. `raw_material_snapshot` is the name/sku/unit captured
 * at write time. Lives on the default (tenant) connection.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $raw_material_id
 * @property array{name: string, sku: string, unit: string} $raw_material_snapshot
 * @property string $quantity
 * @property string $unit_cost
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read RawMaterial|null $rawMaterial
 */
#[Fillable(['purchase_order_id', 'raw_material_id', 'raw_material_snapshot', 'quantity', 'unit_cost'])]
class PurchaseOrderItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_material_snapshot' => 'array',
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<RawMaterial, $this>
     */
    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
