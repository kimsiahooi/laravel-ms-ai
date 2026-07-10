<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Receive a pending purchase order into a location: atomically post a
 * purchase_receipt IN per line to the Phase-3 ledger, then mark it received.
 */
class ReceivePurchaseOrder
{
    public function __construct(private readonly StockService $stock) {}

    public function handle(PurchaseOrder $order, Location $location, ?User $user = null): PurchaseOrder
    {
        abort_unless(
            $order->status === PurchaseOrderStatus::Pending,
            422,
            'Only a pending purchase order can be received.',
        );

        return DB::transaction(function () use ($order, $location, $user): PurchaseOrder {
            $order->loadMissing('items.rawMaterial');

            foreach ($order->items as $item) {
                abort_if(
                    $item->raw_material_id === null || $item->rawMaterial === null,
                    422,
                    'A line references a raw material that no longer exists.',
                );

                $this->stock->record(
                    $location,
                    $item->rawMaterial,
                    (float) $item->quantity,
                    StockMovementReason::PurchaseReceipt,
                    $user,
                    "PO #{$order->id}",
                );
            }

            $order->update([
                'status' => PurchaseOrderStatus::Received,
                'received_at' => now(),
                'received_location_id' => $location->id,
            ]);

            return $order;
        });
    }
}
