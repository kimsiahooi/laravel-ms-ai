<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A stock transfer document — one stockable moved from a source to a destination
 * location. Its on-hand effect lives in the stock_movements ledger (a transfer_out
 * + a transfer_in), written atomically by StockService. Lives on the default
 * connection, which InitializeTenancyByPath has switched to the tenant database.
 *
 * @property int $id
 * @property int $from_location_id
 * @property int $to_location_id
 * @property string $stockable_type
 * @property int $stockable_id
 * @property string $quantity
 * @property int|null $user_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Location $fromLocation
 * @property-read Location $toLocation
 * @property-read Model $stockable
 * @property-read User|null $user
 */
#[Fillable(['from_location_id', 'to_location_id', 'stockable_type', 'stockable_id', 'quantity', 'user_id', 'notes'])]
class StockTransfer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function stockable(): MorphTo
    {
        return $this->morphTo('stockable');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
