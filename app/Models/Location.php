<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Storage location within a warehouse, in the per-tenant inventory. Lives on the
 * default connection, which InitializeTenancyByPath has switched to the tenant DB.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property string $code
 * @property string|null $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['warehouse_id', 'code', 'name'])]
class Location extends Model
{
    use Searchable;
    use SoftDeletes;

    protected array $searchable = ['code', 'name'];

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
