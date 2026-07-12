<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single gateway for every on-hand mutation. Each call locks the
 * (warehouse, stockable) on-hand row, applies a signed delta, refuses to go
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
        Warehouse $warehouse,
        Model $stockable,
        float $delta,
        StockMovementReason $reason,
        ?User $user = null,
        ?string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use ($warehouse, $stockable, $delta, $reason, $user, $notes): StockMovement {
            $this->applyLockedDelta($warehouse, $stockable, $delta);

            return $this->writeMovement($warehouse, $stockable, $delta, $reason, $user, $notes);
        });
    }

    /**
     * Move a quantity of one stockable from a source to a destination warehouse.
     * Atomic: the source OUT + destination IN + the transfer record all commit
     * together, or none do (so an insufficient source rolls the whole thing back).
     *
     * @throws InsufficientStockException when the source lacks the quantity
     */
    public function transfer(
        Warehouse $from,
        Warehouse $to,
        Model $stockable,
        float $quantity,
        ?User $user = null,
        ?string $notes = null,
    ): StockTransfer {
        return DB::transaction(function () use ($from, $to, $stockable, $quantity, $user, $notes): StockTransfer {
            $this->record($from, $stockable, -$quantity, StockMovementReason::TransferOut, $user, $notes);
            $this->record($to, $stockable, $quantity, StockMovementReason::TransferIn, $user, $notes);

            return StockTransfer::create([
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
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
        Warehouse $warehouse,
        Model $stockable,
        float $target,
        ?User $user = null,
        ?string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use ($warehouse, $stockable, $target, $user, $notes): StockMovement {
            $current = $this->currentQuantity($warehouse, $stockable);
            $delta = $target - $current;

            $this->applyLockedDelta($warehouse, $stockable, $delta);

            return $this->writeMovement($warehouse, $stockable, $delta, StockMovementReason::Adjustment, $user, $notes);
        });
    }

    /**
     * Current on-hand for (warehouse, stockable) WITHOUT a lock — a read-only
     * lookup for display (e.g. the movement/transfer dialogs). Returns 0 when no
     * row exists. Use record()/setLevel()/transfer() for anything that mutates.
     */
    public function onHand(Warehouse $warehouse, Model $stockable): float
    {
        return (float) (WarehouseStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('stockable_type', $stockable->getMorphClass())
            ->where('stockable_id', $stockable->getKey())
            ->value('quantity') ?? 0);
    }

    /**
     * Lock the on-hand row, add $delta, and persist the new level. Returns the
     * pre-delta quantity. MUST run inside a transaction (callers wrap it).
     *
     * @throws InsufficientStockException when the resulting level is negative
     */
    private function applyLockedDelta(Warehouse $warehouse, Model $stockable, float $delta): float
    {
        $stock = $this->lockedStock($warehouse, $stockable);

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
            WarehouseStock::create([
                'warehouse_id' => $warehouse->id,
                'stockable_type' => $stockable->getMorphClass(),
                'stockable_id' => $stockable->getKey(),
                'quantity' => $new,
            ]);
        }

        return $current;
    }

    /** Current on-hand under a row lock (0 when no row exists yet). */
    private function currentQuantity(Warehouse $warehouse, Model $stockable): float
    {
        return (float) ($this->lockedStock($warehouse, $stockable)?->quantity ?? 0);
    }

    /** The on-hand row for (warehouse, stockable), locked FOR UPDATE, or null. */
    private function lockedStock(Warehouse $warehouse, Model $stockable): ?WarehouseStock
    {
        return WarehouseStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('stockable_type', $stockable->getMorphClass())
            ->where('stockable_id', $stockable->getKey())
            ->lockForUpdate()
            ->first();
    }

    /** Append one ledger row with the signed delta. */
    private function writeMovement(
        Warehouse $warehouse,
        Model $stockable,
        float $delta,
        StockMovementReason $reason,
        ?User $user,
        ?string $notes,
    ): StockMovement {
        return StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'stockable_type' => $stockable->getMorphClass(),
            'stockable_id' => $stockable->getKey(),
            'quantity' => $delta,
            'reason' => $reason,
            'user_id' => $user?->id,
            'notes' => $notes,
        ]);
    }
}
