<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReturnStatus;
use App\Enums\StockMovementReason;
use App\Models\PurchaseReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Complete a pending purchase return: atomically post a purchase_return OUT per
 * line (returning received raw materials to the supplier) from a chosen warehouse,
 * then mark it completed.
 */
class CompletePurchaseReturn
{
    public function __construct(private readonly StockService $stock) {}

    public function handle(PurchaseReturn $return, Warehouse $warehouse, ?User $user = null): PurchaseReturn
    {
        abort_unless(
            $return->status === ReturnStatus::Pending,
            422,
            'Only a pending purchase return can be completed.',
        );

        return DB::transaction(function () use ($return, $warehouse, $user): PurchaseReturn {
            $return->loadMissing('items.rawMaterial');

            foreach ($return->items as $item) {
                abort_if(
                    $item->raw_material_id === null || $item->rawMaterial === null,
                    422,
                    'A line references a raw material that no longer exists.',
                );

                $this->stock->record(
                    $warehouse,
                    $item->rawMaterial,
                    -(float) $item->quantity,
                    StockMovementReason::PurchaseReturn,
                    $user,
                    "Purchase return #{$return->id}",
                );
            }

            $return->update([
                'status' => ReturnStatus::Completed,
                'completed_at' => now(),
                'completed_warehouse_id' => $warehouse->id,
            ]);

            return $return;
        });
    }
}
