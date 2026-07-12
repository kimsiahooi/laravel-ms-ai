<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ProductionOrderItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One exploded BOM line of a production order, for the table + detail view. */
#[TypeScript]
class ProductionOrderItemData extends Data
{
    public function __construct(
        public int $id,
        public ?int $raw_material_id,
        /** Snapshot name (survives raw-material edits/deletes). */
        public string $name,
        public float $quantity_per_unit,
        public float $quantity_required,
    ) {}

    public static function fromProductionOrderItem(ProductionOrderItem $item): self
    {
        return new self(
            id: $item->id,
            raw_material_id: $item->raw_material_id,
            name: $item->raw_material_snapshot['name'] ?? '—',
            quantity_per_unit: (float) $item->quantity_per_unit,
            quantity_required: (float) $item->quantity_required,
        );
    }
}
