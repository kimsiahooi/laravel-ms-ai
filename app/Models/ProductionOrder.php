<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductionOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A production order. Lives on the default connection, which InitializeTenancyByPath
 * has switched to the tenant database.
 *
 * @property int $id
 * @property int|null $product_id
 * @property array{name: string, sku: string, unit: string} $product_snapshot
 * @property string $quantity
 * @property ProductionOrderStatus $status
 * @property string|null $notes
 * @property int|null $user_id
 * @property Carbon|null $completed_at
 * @property int|null $completed_location_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Product|null $product
 * @property-read Collection<int, ProductionOrderItem> $items
 * @property-read User|null $user
 * @property-read Location|null $completedLocation
 */
#[Fillable(['product_id', 'product_snapshot', 'quantity', 'status', 'notes', 'user_id', 'completed_at', 'completed_location_id'])]
class ProductionOrder extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_snapshot' => 'array',
            'quantity' => 'decimal:4',
            'status' => ProductionOrderStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ProductionOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function completedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'completed_location_id');
    }
}
