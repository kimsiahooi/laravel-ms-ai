<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Warehouse in the per-tenant inventory. Lives on the default connection, which
 * InitializeTenancyByPath has switched to the tenant database.
 *
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string|null $address
 */
#[Fillable(['name', 'code', 'address'])]
class Warehouse extends Model
{
    use Searchable;
    use SoftDeletes;

    protected array $searchable = ['name', 'code'];
}
