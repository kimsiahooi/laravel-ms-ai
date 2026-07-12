<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StockMovementReason;
use App\Enums\StockTakeStatus;
use App\Models\StockTake;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Post a draft stock take: for each line, record the count and set the warehouse's
 * on-hand to the counted quantity (a stock_take adjustment in the ledger). Storing
 * variance = counted − system for history. Atomic; marks the take posted.
 */
class PostStockTake
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array<int, array{id: int, counted_qty: float|int|string}>  $counts
     */
    public function handle(StockTake $take, array $counts, ?User $user = null): StockTake
    {
        abort_unless(
            $take->status === StockTakeStatus::Draft,
            422,
            'Only a draft stock take can be posted.',
        );

        return DB::transaction(function () use ($take, $counts, $user): StockTake {
            $take->loadMissing(['items.stockable', 'warehouse']);
            $countById = collect($counts)->keyBy('id');

            foreach ($take->items as $item) {
                $counted = (float) ($countById[$item->id]['counted_qty'] ?? $item->counted_qty);
                $variance = $counted - (float) $item->system_qty;

                $item->update(['counted_qty' => $counted, 'variance' => $variance]);

                if ($item->stockable === null) {
                    continue;
                }

                // Set on-hand to the counted quantity (delta vs current on-hand).
                // counted >= 0, so this never drives on-hand negative.
                $delta = $counted - $this->stock->onHand($take->warehouse, $item->stockable);

                if ($delta !== 0.0) {
                    $this->stock->record(
                        $take->warehouse,
                        $item->stockable,
                        $delta,
                        StockMovementReason::StockTake,
                        $user,
                        "Stock take #{$take->id}",
                    );
                }
            }

            $take->update([
                'status' => StockTakeStatus::Posted,
                'counted_at' => now(),
                'user_id' => $user?->id,
            ]);

            return $take;
        });
    }
}
