<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Warehouse;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The warehouse list-item payload. snake_case property names keep the serialized
 * JSON (and the generated TS) aligned with the other catalog resources.
 * #[TypeScript] makes the transformer emit App.Data.WarehouseData.
 */
#[TypeScript]
class WarehouseData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $code,
        public ?string $address,
        public string $created_at,
    ) {}

    public static function fromWarehouse(Warehouse $warehouse): self
    {
        return new self(
            id: $warehouse->id,
            name: $warehouse->name,
            code: $warehouse->code,
            address: $warehouse->address,
            created_at: $warehouse->created_at->toISOString(),
        );
    }
}
