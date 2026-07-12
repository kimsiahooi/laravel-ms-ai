<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnStatus;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A return of products from a customer. Lives on the default (tenant) connection.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property ReturnStatus $status
 * @property string|null $notes
 * @property int|null $user_id
 * @property Carbon|null $completed_at
 * @property int|null $completed_warehouse_id
 * @property-read Customer|null $customer
 * @property-read Collection<int, SalesReturnItem> $items
 */
#[Fillable([
    'customer_id', 'status', 'notes', 'user_id',
    'completed_at', 'completed_warehouse_id',
])]
class SalesReturn extends Model
{
    use Searchable;
    use SoftDeletes;

    /** @var array<int, string> */
    protected array $searchable = ['id', 'notes'];

    /** @var array<string, array<int, string>> */
    protected array $searchableRelations = ['customer' => ['name']];

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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<SalesReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
