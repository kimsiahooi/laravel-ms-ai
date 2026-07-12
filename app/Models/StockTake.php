<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockTakeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A physical stock count for one warehouse. Lives on the default (tenant) connection.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property StockTakeStatus $status
 * @property int|null $user_id
 * @property Carbon|null $counted_at
 * @property string|null $notes
 * @property-read Warehouse $warehouse
 * @property-read Collection<int, StockTakeItem> $items
 * @property-read User|null $user
 */
#[Fillable(['warehouse_id', 'status', 'user_id', 'counted_at', 'notes'])]
class StockTake extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StockTakeStatus::class,
            'counted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<StockTakeItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockTakeItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
