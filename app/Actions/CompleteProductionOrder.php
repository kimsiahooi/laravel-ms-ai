<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProductionOrderStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Models\Location;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Complete a pending production order at a location: atomically post a
 * production_consume OUT for every exploded BOM line, then a production_output IN
 * for the finished product, then mark it completed. If the location is short on
 * any material, StockService throws and the whole completion rolls back — no
 * stock moves and the order stays pending.
 */
class CompleteProductionOrder
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @throws InsufficientStockException when the location cannot cover a material
     */
    public function handle(ProductionOrder $order, Location $location, ?User $user = null): ProductionOrder
    {
        abort_unless(
            $order->status === ProductionOrderStatus::Pending,
            422,
            'Only a pending production order can be completed.',
        );

        abort_if(
            $order->product_id === null || $order->product === null,
            422,
            'This production order references a product that no longer exists.',
        );

        return DB::transaction(function () use ($order, $location, $user): ProductionOrder {
            $order->loadMissing('items.rawMaterial', 'product');

            // Consume every material first; a shortage throws here and rolls back
            // before any output is produced.
            foreach ($order->items as $item) {
                abort_if(
                    $item->raw_material_id === null || $item->rawMaterial === null,
                    422,
                    'A material line references a raw material that no longer exists.',
                );

                $this->stock->record(
                    $location,
                    $item->rawMaterial,
                    -(float) $item->quantity_required,
                    StockMovementReason::ProductionConsume,
                    $user,
                    "MO #{$order->id}",
                );
            }

            // Produce the finished goods into the same location.
            $this->stock->record(
                $location,
                $order->product,
                (float) $order->quantity,
                StockMovementReason::ProductionOutput,
                $user,
                "MO #{$order->id}",
            );

            $order->update([
                'status' => ProductionOrderStatus::Completed,
                'completed_at' => now(),
                'completed_location_id' => $location->id,
            ]);

            return $order;
        });
    }
}
