<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\PurchaseOrderItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One purchase-order line for the table + edit form. */
#[TypeScript]
class PurchaseOrderItemData extends Data
{
    public function __construct(
        public int $id,
        public ?int $raw_material_id,
        /** Snapshot name (survives raw-material edits/deletes). */
        public string $name,
        public float $quantity,
        public float $unit_cost,
    ) {}

    public static function fromPurchaseOrderItem(PurchaseOrderItem $item): self
    {
        return new self(
            id: $item->id,
            raw_material_id: $item->raw_material_id,
            name: $item->raw_material_snapshot['name'] ?? '—',
            quantity: (float) $item->quantity,
            unit_cost: (float) $item->unit_cost,
        );
    }
}
