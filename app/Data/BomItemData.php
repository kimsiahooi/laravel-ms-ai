<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\BomItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One BOM line: a raw material + per-unit quantity for a product. */
#[TypeScript]
class BomItemData extends Data
{
    public function __construct(
        public int $id,
        public int $raw_material_id,
        public string $name,
        public float $quantity,
    ) {}

    public static function fromBomItem(BomItem $item): self
    {
        return new self(
            id: $item->id,
            raw_material_id: $item->raw_material_id,
            name: $item->rawMaterial?->name ?? '—',
            quantity: (float) $item->quantity,
        );
    }
}
