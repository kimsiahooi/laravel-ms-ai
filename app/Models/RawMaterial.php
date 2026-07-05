<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Raw material in the per-tenant catalog. Lives on the default connection, which
 * InitializeTenancyByPath has switched to the tenant database.
 *
 * @property int $id
 * @property string $name
 * @property string $sku
 * @property string $unit
 * @property string $min_stock
 */
#[Fillable(['name', 'sku', 'unit', 'min_stock'])]
class RawMaterial extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_stock' => 'decimal:4'];
    }
}
