<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A sales return flattened for the list + edit form. */
#[TypeScript]
class SalesReturnData extends Data
{
    /**
     * @param  array<int, SalesReturnItemData>  $items
     */
    public function __construct(
        public int $id,
        public ?string $customer,
        public string $status,
        public string $status_label,
        public int $item_count,
        public float $total_quantity,
        public ?string $completed_at,
        public string $created_at,
        #[DataCollectionOf(SalesReturnItemData::class)]
        public array $items,
    ) {}

    public static function fromSalesReturn(SalesReturn $return): self
    {
        $items = $return->items->map(
            fn (SalesReturnItem $item): SalesReturnItemData => SalesReturnItemData::from($item),
        );

        return new self(
            id: $return->id,
            customer: $return->customer?->name,
            status: $return->status->value,
            status_label: $return->status->label(),
            item_count: $items->count(),
            total_quantity: (float) $items->sum(fn (SalesReturnItemData $item): float => $item->quantity),
            completed_at: $return->completed_at?->toISOString(),
            created_at: $return->created_at->toISOString(),
            items: $items->all(),
        );
    }
}
