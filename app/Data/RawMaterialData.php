<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\RawMaterial;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The raw-material list-item payload. snake_case property names keep the
 * serialized JSON (and the generated TS) byte-identical to the previous
 * hand-mapped array. #[TypeScript] makes the transformer emit
 * App.Data.RawMaterialData.
 */
#[TypeScript]
class RawMaterialData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $sku,
        public string $unit,
        public string $min_stock,
        public string $created_at,
    ) {}

    public static function fromRawMaterial(RawMaterial $rawMaterial): self
    {
        return new self(
            id: $rawMaterial->id,
            name: $rawMaterial->name,
            sku: $rawMaterial->sku,
            unit: $rawMaterial->unit,
            min_stock: $rawMaterial->min_stock,
            created_at: $rawMaterial->created_at->toISOString(),
        );
    }
}
