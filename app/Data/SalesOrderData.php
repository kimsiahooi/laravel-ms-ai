<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A sales order flattened for the list + edit form. */
#[TypeScript]
class SalesOrderData extends Data
{
    /**
     * @param  array<int, SalesOrderItemData>  $items
     */
    public function __construct(
        public int $id,
        public ?string $customer,
        public string $status,
        public string $status_label,
        public string $currency,
        public int $item_count,
        public float $total,
        public ?string $fulfilled_at,
        public string $created_at,
        #[DataCollectionOf(SalesOrderItemData::class)]
        public array $items,
    ) {}

    public static function fromSalesOrder(SalesOrder $order): self
    {
        $items = $order->items->map(
            fn (SalesOrderItem $item): SalesOrderItemData => SalesOrderItemData::from($item),
        );

        return new self(
            id: $order->id,
            customer: $order->customer?->name,
            status: $order->status->value,
            status_label: $order->status->label(),
            currency: $order->currency,
            item_count: $items->count(),
            total: (float) $items->sum(fn (SalesOrderItemData $item): float => $item->quantity * $item->unit_price),
            fulfilled_at: $order->fulfilled_at?->toISOString(),
            created_at: $order->created_at->toISOString(),
            items: $items->all(),
        );
    }
}
