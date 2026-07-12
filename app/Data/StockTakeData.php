<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\StockTake;
use App\Models\StockTakeItem;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A stock take flattened for the list + count page. */
#[TypeScript]
class StockTakeData extends Data
{
    /**
     * @param  array<int, StockTakeItemData>  $items
     */
    public function __construct(
        public int $id,
        /** "Site · Warehouse". */
        public ?string $warehouse,
        public string $status,
        public string $status_label,
        public int $item_count,
        /** Sum of line variances (counted − system). */
        public float $total_variance,
        public ?string $notes,
        public ?string $counted_at,
        public string $created_at,
        #[DataCollectionOf(StockTakeItemData::class)]
        public array $items,
    ) {}

    public static function fromStockTake(StockTake $take): self
    {
        $items = $take->items->map(
            fn (StockTakeItem $item): StockTakeItemData => StockTakeItemData::from($item),
        );

        $warehouse = $take->warehouse;

        return new self(
            id: $take->id,
            warehouse: $warehouse
                ? ($warehouse->location?->name ?? '?').' · '.$warehouse->name
                : null,
            status: $take->status->value,
            status_label: $take->status->label(),
            item_count: $items->count(),
            total_variance: (float) $items->sum(fn (StockTakeItemData $item): float => $item->variance),
            notes: $take->notes,
            counted_at: $take->counted_at?->toISOString(),
            created_at: $take->created_at->toISOString(),
            items: $items->all(),
        );
    }
}
