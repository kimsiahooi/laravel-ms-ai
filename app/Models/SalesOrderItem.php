<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a sales order. `product_snapshot` is the name/sku/unit captured at
 * write time. Lives on the default (tenant) connection.
 *
 * @property int $id
 * @property int $sales_order_id
 * @property int|null $product_id
 * @property array{name: string, sku: string, unit: string} $product_snapshot
 * @property string $quantity
 * @property string $unit_price
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SalesOrder $salesOrder
 * @property-read Product|null $product
 */
#[Fillable(['sales_order_id', 'product_id', 'product_snapshot', 'quantity', 'unit_price'])]
class SalesOrderItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_snapshot' => 'array',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
