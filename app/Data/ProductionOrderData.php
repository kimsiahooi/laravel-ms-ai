<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderItem;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A production order flattened for the list + detail view. */
#[TypeScript]
class ProductionOrderData extends Data
{
    /**
     * @param  array<int, ProductionOrderItemData>  $items
     */
    public function __construct(
        public int $id,
        /** Snapshot name (survives product edits/deletes). */
        public string $product,
        public float $quantity,
        public string $status,
        public string $status_label,
        public int $item_count,
        public ?string $completed_at,
        public string $created_at,
        #[DataCollectionOf(ProductionOrderItemData::class)]
        public array $items,
    ) {}

    public static function fromProductionOrder(ProductionOrder $order): self
    {
        $items = $order->items->map(
            fn (ProductionOrderItem $item): ProductionOrderItemData => ProductionOrderItemData::from($item),
        );

        return new self(
            id: $order->id,
            product: $order->product_snapshot['name'] ?? '—',
            quantity: (float) $order->quantity,
            status: $order->status->value,
            status_label: $order->status->label(),
            item_count: $items->count(),
            completed_at: $order->completed_at?->toISOString(),
            created_at: $order->created_at->toISOString(),
            items: $items->all(),
        );
    }
}
