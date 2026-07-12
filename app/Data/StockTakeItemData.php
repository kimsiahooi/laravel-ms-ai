<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\StockTakeItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One counted line of a stock take for the count table. */
#[TypeScript]
class StockTakeItemData extends Data
{
    public function __construct(
        public int $id,
        /** Snapshot name (survives item edits/deletes). */
        public string $name,
        public ?string $sku,
        public string $unit,
        public float $system_qty,
        public float $counted_qty,
        public float $variance,
    ) {}

    public static function fromStockTakeItem(StockTakeItem $item): self
    {
        return new self(
            id: $item->id,
            name: $item->stockable_snapshot['name'] ?? '—',
            sku: $item->stockable_snapshot['sku'] ?? null,
            unit: $item->stockable_snapshot['unit'] ?? '',
            system_qty: (float) $item->system_qty,
            counted_qty: (float) $item->counted_qty,
            variance: (float) $item->variance,
        );
    }
}
