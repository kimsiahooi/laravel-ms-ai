<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A purchase order flattened for the list + edit form. */
#[TypeScript]
class PurchaseOrderData extends Data
{
    /**
     * @param  array<int, PurchaseOrderItemData>  $items
     */
    public function __construct(
        public int $id,
        public ?string $supplier,
        public string $status,
        public string $status_label,
        public string $currency,
        public int $item_count,
        public float $total,
        public ?string $received_at,
        public string $created_at,
        #[DataCollectionOf(PurchaseOrderItemData::class)]
        public array $items,
    ) {}

    public static function fromPurchaseOrder(PurchaseOrder $order): self
    {
        $items = $order->items->map(
            fn (PurchaseOrderItem $item): PurchaseOrderItemData => PurchaseOrderItemData::from($item),
        );

        return new self(
            id: $order->id,
            supplier: $order->supplier?->name,
            status: $order->status->value,
            status_label: $order->status->label(),
            currency: $order->currency,
            item_count: $items->count(),
            total: (float) $items->sum(fn (PurchaseOrderItemData $item): float => $item->quantity * $item->unit_cost),
            received_at: $order->received_at?->toISOString(),
            created_at: $order->created_at->toISOString(),
            items: $items->all(),
        );
    }
}
