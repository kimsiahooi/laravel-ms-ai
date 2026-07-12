<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\PurchaseReturnItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One purchase-return line for the table + edit form. */
#[TypeScript]
class PurchaseReturnItemData extends Data
{
    public function __construct(
        public int $id,
        public ?int $raw_material_id,
        /** Snapshot name (survives raw-material edits/deletes). */
        public string $name,
        public float $quantity,
    ) {}

    public static function fromPurchaseReturnItem(PurchaseReturnItem $item): self
    {
        return new self(
            id: $item->id,
            raw_material_id: $item->raw_material_id,
            name: $item->raw_material_snapshot['name'] ?? '—',
            quantity: (float) $item->quantity,
        );
    }
}
