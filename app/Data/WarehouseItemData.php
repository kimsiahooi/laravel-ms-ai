<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the warehouse detail table: a catalog item in the context of a
 * warehouse (whether or not it currently holds stock). Built from a raw UNION
 * row (stdClass) — the union carries only the lowercase `stockable_type` alias
 * and string DB scalars, so `type`/`needs_reorder` are derived and numerics cast
 * here. A bare `::from($row)` would THROW (missing type, needs_reorder). Mirrors
 * StockMovementData::fromStockMovement.
 */
#[TypeScript]
class WarehouseItemData extends Data
{
    public function __construct(
        public string $stockable_type, // "product" | "raw_material" (alias — the PUT target)
        public int $stockable_id,
        public string $item,
        public ?string $sku,
        public string $type,           // "Product" | "Raw material" (label)
        public string $unit,
        public float $on_hand,         // quantity in THIS warehouse (0 if none)
        public float $min_stock,       // THIS warehouse's threshold (0 if unset)
        public bool $needs_reorder,    // min_stock > 0 && on_hand < min_stock
    ) {}

    public static function fromRow(object $row): self
    {
        $onHand = (float) $row->on_hand;
        $min = (float) $row->min_stock;

        return new self(
            stockable_type: $row->stockable_type,
            stockable_id: (int) $row->stockable_id,
            item: $row->item,
            sku: $row->sku,
            type: $row->stockable_type === 'product' ? 'Product' : 'Raw material',
            unit: $row->unit,
            on_hand: $onHand,
            min_stock: $min,
            needs_reorder: $min > 0 && $onHand < $min,
        );
    }
}
