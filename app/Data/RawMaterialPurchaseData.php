<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\PurchaseOrderItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One received purchase-order line for a raw material — a single entry in its
 * "which supplier did this come from" history (R10). Derived from received POs.
 */
#[TypeScript]
class RawMaterialPurchaseData extends Data
{
    public function __construct(
        public int $id,
        public int $po_id,
        public ?string $supplier,
        public ?string $received_at,
        public float $quantity,
        public float $unit_cost,
        public string $currency,
    ) {}

    public static function fromPurchaseOrderItem(PurchaseOrderItem $item): self
    {
        return new self(
            id: $item->id,
            po_id: $item->purchase_order_id,
            supplier: $item->purchaseOrder->supplier?->name,
            received_at: $item->purchaseOrder->received_at?->toISOString(),
            quantity: (float) $item->quantity,
            unit_cost: (float) $item->unit_cost,
            currency: $item->purchaseOrder->currency,
        );
    }
}
