<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A return of received raw materials to a supplier. Lives on the default (tenant)
 * connection.
 *
 * @property int $id
 * @property int|null $supplier_id
 * @property ReturnStatus $status
 * @property string|null $notes
 * @property int|null $user_id
 * @property Carbon|null $completed_at
 * @property int|null $completed_warehouse_id
 * @property-read Supplier|null $supplier
 * @property-read Collection<int, PurchaseReturnItem> $items
 */
#[Fillable([
    'supplier_id', 'status', 'notes', 'user_id',
    'completed_at', 'completed_warehouse_id',
])]
class PurchaseReturn extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<PurchaseReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
