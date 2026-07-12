<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a sales return. `product_snapshot` is the name/sku/unit captured at
 * write time. Lives on the default (tenant) connection.
 *
 * @property int $id
 * @property int $sales_return_id
 * @property int|null $product_id
 * @property array{name: string, sku: string, unit: string} $product_snapshot
 * @property string $quantity
 * @property-read SalesReturn $salesReturn
 * @property-read Product|null $product
 */
#[Fillable(['sales_return_id', 'product_id', 'product_snapshot', 'quantity'])]
class SalesReturnItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_snapshot' => 'array',
            'quantity' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<SalesReturn, $this>
     */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
