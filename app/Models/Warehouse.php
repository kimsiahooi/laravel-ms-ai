<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\BlockedByDependentsException;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Warehouse in the per-tenant inventory — a stock-holding building that belongs
 * to a location (site). Lives on the default connection, which
 * InitializeTenancyByPath has switched to the tenant database.
 *
 * @property int $id
 * @property int $location_id
 * @property string $name
 * @property string|null $code
 * @property string|null $address
 * @property-read Location $location
 */
#[Fillable(['location_id', 'name', 'code', 'address'])]
class Warehouse extends Model
{
    use Searchable;
    use SoftDeletes;

    protected array $searchable = ['name', 'code', 'address'];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return HasMany<WarehouseStock, $this>
     */
    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    protected static function booted(): void
    {
        // Block deletion while the warehouse still holds on-hand stock. Zero it
        // out (adjust/transfer) first. Backstopped by restrictOnDelete for hard deletes.
        static::deleting(function (Warehouse $warehouse): void {
            if ($warehouse->warehouseStocks()->where('quantity', '!=', 0)->exists()) {
                throw new BlockedByDependentsException(
                    'Move or adjust this warehouse’s stock to zero before deleting it.'
                );
            }
        });
    }
}
