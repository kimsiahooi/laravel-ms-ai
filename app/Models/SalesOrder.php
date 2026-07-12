<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SalesOrderStatus;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A sales order. Lives on the default connection, which InitializeTenancyByPath
 * has switched to the tenant database.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property SalesOrderStatus $status
 * @property string $currency
 * @property string|null $notes
 * @property int|null $user_id
 * @property Carbon|null $fulfilled_at
 * @property int|null $fulfilled_warehouse_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Customer|null $customer
 * @property-read Collection<int, SalesOrderItem> $items
 * @property-read User|null $user
 * @property-read Warehouse|null $fulfilledWarehouse
 */
#[Fillable(['customer_id', 'status', 'currency', 'notes', 'user_id', 'fulfilled_at', 'fulfilled_warehouse_id'])]
class SalesOrder extends Model
{
    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SalesOrderStatus::class,
            'fulfilled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<SalesOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
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
    public function fulfilledWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'fulfilled_warehouse_id');
    }
}
