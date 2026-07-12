<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReturnStatus;
use App\Enums\StockMovementReason;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Complete a pending sales return: atomically post a sales_return IN per line
 * (products coming back from a customer) into a chosen warehouse, then mark it
 * completed.
 */
class CompleteSalesReturn
{
    public function __construct(private readonly StockService $stock) {}

    public function handle(SalesReturn $return, Warehouse $warehouse, ?User $user = null): SalesReturn
    {
        abort_unless(
            $return->status === ReturnStatus::Pending,
            422,
            'Only a pending sales return can be completed.',
        );

        return DB::transaction(function () use ($return, $warehouse, $user): SalesReturn {
            $return->loadMissing('items.product');

            foreach ($return->items as $item) {
                abort_if(
                    $item->product_id === null || $item->product === null,
                    422,
                    'A line references a product that no longer exists.',
                );

                $this->stock->record(
                    $warehouse,
                    $item->product,
                    (float) $item->quantity,
                    StockMovementReason::SalesReturn,
                    $user,
                    "Sales return #{$return->id}",
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
