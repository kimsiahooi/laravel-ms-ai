<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a purchase return. `raw_material_snapshot` is the name/sku/unit
 * captured at write time. Lives on the default (tenant) connection.
 *
 * @property int $id
 * @property int $purchase_return_id
 * @property int|null $raw_material_id
 * @property array{name: string, sku: string, unit: string} $raw_material_snapshot
 * @property string $quantity
 * @property-read PurchaseReturn $purchaseReturn
 * @property-read RawMaterial|null $rawMaterial
 */
#[Fillable(['purchase_return_id', 'raw_material_id', 'raw_material_snapshot', 'quantity'])]
class PurchaseReturnItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_material_snapshot' => 'array',
            'quantity' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<PurchaseReturn, $this>
     */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    /**
     * @return BelongsTo<RawMaterial, $this>
     */
    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
