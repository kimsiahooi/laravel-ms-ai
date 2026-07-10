<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A purchase order. Lives on the default connection, which InitializeTenancyByPath
 * has switched to the tenant database.
 *
 * @property int $id
 * @property int|null $supplier_id
 * @property PurchaseOrderStatus $status
 * @property string $currency
 * @property string|null $notes
 * @property int|null $user_id
 * @property Carbon|null $received_at
 * @property int|null $received_location_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Supplier|null $supplier
 * @property-read Collection<int, PurchaseOrderItem> $items
 * @property-read User|null $user
 * @property-read Location|null $receivedLocation
 */
#[Fillable(['supplier_id', 'status', 'currency', 'notes', 'user_id', 'received_at', 'received_location_id'])]
class PurchaseOrder extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'received_at' => 'datetime',
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
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
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
    public function receivedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'received_location_id');
    }
}
