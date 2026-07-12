<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\Searchable;
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
 * @property int|null $received_warehouse_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Supplier|null $supplier
 * @property-read Collection<int, PurchaseOrderItem> $items
 * @property-read User|null $user
 * @property-read Warehouse|null $receivedWarehouse
 */
#[Fillable(['supplier_id', 'status', 'currency', 'notes', 'user_id', 'received_at', 'received_warehouse_id'])]
class PurchaseOrder extends Model
{
    use RecordsActivity;
    use Searchable;
    use SoftDeletes;

    /** @var array<int, string> */
    protected array $searchable = ['id', 'notes'];

    /** @var array<string, array<int, string>> */
    protected array $searchableRelations = ['supplier' => ['name']];

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
     * @return BelongsTo<Warehouse, $this>
     */
    public function receivedWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'received_warehouse_id');
    }
}
