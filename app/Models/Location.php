<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\BlockedByDependentsException;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Site / branch / outlet in the per-tenant inventory — the top of the hierarchy.
 * Registered first and owns warehouses. Lives on the default connection, which
 * InitializeTenancyByPath has switched to the tenant DB.
 *
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string|null $address
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'code', 'address'])]
class Location extends Model
{
    use Searchable;
    use SoftDeletes;

    protected array $searchable = ['name', 'code', 'address'];

    /**
     * @return HasMany<Warehouse, $this>
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    protected static function booted(): void
    {
        // Block deletion while the site still owns warehouses (the FK restrict
        // only backstops hard deletes; this covers the soft-delete path).
        static::deleting(function (Location $location): void {
            if ($location->warehouses()->exists()) {
                throw new BlockedByDependentsException(
                    'Remove this location’s warehouses before deleting it.'
                );
            }
        });
    }
}
