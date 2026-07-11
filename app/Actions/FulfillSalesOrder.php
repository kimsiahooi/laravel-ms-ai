<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Fulfill a pending sales order from a warehouse: atomically post a
 * sales_fulfillment OUT per line to the Phase-3 ledger, then mark it fulfilled.
 * If the warehouse is short on any line, StockService throws and the whole fulfill
 * rolls back — no stock leaves, the order stays pending.
 */
class FulfillSalesOrder
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @throws InsufficientStockException when the warehouse cannot cover a line
     */
    public function handle(SalesOrder $order, Warehouse $warehouse, ?User $user = null): SalesOrder
    {
        abort_unless(
            $order->status === SalesOrderStatus::Pending,
            422,
            'Only a pending sales order can be fulfilled.',
        );

        return DB::transaction(function () use ($order, $warehouse, $user): SalesOrder {
            $order->loadMissing('items.product');

            foreach ($order->items as $item) {
                abort_if(
                    $item->product_id === null || $item->product === null,
                    422,
                    'A line references a product that no longer exists.',
                );

                $this->stock->record(
                    $warehouse,
                    $item->product,
                    -(float) $item->quantity,
                    StockMovementReason::SalesFulfillment,
                    $user,
                    "SO #{$order->id}",
                );
            }

            $order->update([
                'status' => SalesOrderStatus::Fulfilled,
                'fulfilled_at' => now(),
                'fulfilled_warehouse_id' => $warehouse->id,
            ]);

            return $order;
        });
    }
}
