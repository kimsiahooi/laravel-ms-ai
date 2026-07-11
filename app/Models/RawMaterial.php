<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Searchable;
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
 */
#[Fillable(['name', 'sku', 'unit'])]
class RawMaterial extends Model
{
    use Searchable;
    use SoftDeletes;

    protected array $searchable = ['name', 'sku'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
