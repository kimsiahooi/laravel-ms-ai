<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Models\Location;
use App\Models\LocationStock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single gateway for every on-hand mutation. Each call locks the
 * (location, stockable) on-hand row, applies a signed delta, refuses to go
 * negative, and appends one ledger row — all inside one DB transaction so the
 * materialized on-hand and the ledger never drift apart.
 */
class StockService
{
    /**
     * Apply a signed delta (+ in / − out) to on-hand and append a ledger row.
     *
     * @throws InsufficientStockException when the delta would drive on-hand below zero
     */
    public function record(
        Location $location,
        Model $stockable,
        float $delta,
        StockMovementReason $reason,
        ?User $user = null,
        ?string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use ($location, $stockable, $delta, $reason, $user, $notes): StockMovement {
            $this->applyLockedDelta($location, $stockable, $delta);

            return $this->writeMovement($location, $stockable, $delta, $reason, $user, $notes);
        });
    }

    /**
     * Move a quantity of one stockable from a source to a destination location.
     * Atomic: the source OUT + destination IN + the transfer record all commit
     * together, or none do (so an insufficient source rolls the whole thing back).
     *
     * @throws InsufficientStockException when the source lacks the quantity
     */
    public function transfer(
        Location $from,
        Location $to,
        Model $stockable,
        float $quantity,
        ?User $user = null,
        ?string $notes = null,
    ): StockTransfer {
        return DB::transaction(function () use ($from, $to, $stockable, $quantity, $user, $notes): StockTransfer {
            $this->record($from, $stockable, -$quantity, StockMovementReason::TransferOut, $user, $notes);
            $this->record($to, $stockable, $quantity, StockMovementReason::TransferIn, $user, $notes);

            return StockTransfer::create([
                'from_location_id' => $from->id,
                'to_location_id' => $to->id,
                'stockable_type' => $stockable->getMorphClass(),
                'stockable_id' => $stockable->getKey(),
                'quantity' => $quantity,
                'user_id' => $user?->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Set on-hand to an absolute target, recording the computed signed delta as
     * an Adjustment. Reads the current level under the same lock, so concurrent
     * callers can't race the target.
     *
     * @throws InsufficientStockException when the target is negative
     */
    public function setLevel(
        Location $location,
        Model $stockable,
        float $target,
        ?User $user = null,
        ?string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use ($location, $stockable, $target, $user, $notes): StockMovement {
            $current = $this->currentQuantity($location, $stockable);
            $delta = $target - $current;

            $this->applyLockedDelta($location, $stockable, $delta);

            return $this->writeMovement($location, $stockable, $delta, StockMovementReason::Adjustment, $user, $notes);
        });
    }

    /**
     * Lock the on-hand row, add $delta, and persist the new level. Returns the
     * pre-delta quantity. MUST run inside a transaction (callers wrap it).
     *
     * @throws InsufficientStockException when the resulting level is negative
     */
    private function applyLockedDelta(Location $location, Model $stockable, float $delta): float
    {
        $stock = $this->lockedStock($location, $stockable);

        $current = (float) ($stock?->quantity ?? 0);
        $new = $current + $delta;

        if ($new < 0) {
            throw new InsufficientStockException(
                'Movement would drive on-hand below zero.',
            );
        }

        if ($stock !== null) {
            $stock->update(['quantity' => $new]);
        } else {
            LocationStock::create([
                'location_id' => $location->id,
                'stockable_type' => $stockable->getMorphClass(),
                'stockable_id' => $stockable->getKey(),
                'quantity' => $new,
            ]);
        }

        return $current;
    }

    /** Current on-hand under a row lock (0 when no row exists yet). */
    private function currentQuantity(Location $location, Model $stockable): float
    {
        return (float) ($this->lockedStock($location, $stockable)?->quantity ?? 0);
    }

    /** The on-hand row for (location, stockable), locked FOR UPDATE, or null. */
    private function lockedStock(Location $location, Model $stockable): ?LocationStock
    {
        return LocationStock::query()
            ->where('location_id', $location->id)
            ->where('stockable_type', $stockable->getMorphClass())
            ->where('stockable_id', $stockable->getKey())
            ->lockForUpdate()
            ->first();
    }

    /** Append one ledger row with the signed delta. */
    private function writeMovement(
        Location $location,
        Model $stockable,
        float $delta,
        StockMovementReason $reason,
        ?User $user,
        ?string $notes,
    ): StockMovement {
        return StockMovement::create([
            'location_id' => $location->id,
            'stockable_type' => $stockable->getMorphClass(),
            'stockable_id' => $stockable->getKey(),
            'quantity' => $delta,
            'reason' => $reason,
            'user_id' => $user?->id,
            'notes' => $notes,
        ]);
    }
}
