<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A purchase return flattened for the list + edit form. */
#[TypeScript]
class PurchaseReturnData extends Data
{
    /**
     * @param  array<int, PurchaseReturnItemData>  $items
     */
    public function __construct(
        public int $id,
        public ?string $supplier,
        public ?int $supplier_id,
        public string $status,
        public string $status_label,
        public int $item_count,
        public float $total_quantity,
        public ?string $notes,
        public ?string $completed_at,
        public string $created_at,
        #[DataCollectionOf(PurchaseReturnItemData::class)]
        public array $items,
    ) {}

    public static function fromPurchaseReturn(PurchaseReturn $return): self
    {
        $items = $return->items->map(
            fn (PurchaseReturnItem $item): PurchaseReturnItemData => PurchaseReturnItemData::from($item),
        );

        return new self(
            id: $return->id,
            supplier: $return->supplier?->name,
            supplier_id: $return->supplier_id,
            status: $return->status->value,
            status_label: $return->status->label(),
            item_count: $items->count(),
            total_quantity: (float) $items->sum(fn (PurchaseReturnItemData $item): float => $item->quantity),
            notes: $return->notes,
            completed_at: $return->completed_at?->toISOString(),
            created_at: $return->created_at->toISOString(),
            items: $items->all(),
        );
    }
}
