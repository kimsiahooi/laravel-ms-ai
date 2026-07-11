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
        public int $location_id,
        /** The parent site's name, for the table's Location column. */
        public ?string $location,
        public string $name,
        public ?string $code,
        public ?string $address,
        public string $created_at,
    ) {}

    public static function fromWarehouse(Warehouse $warehouse): self
    {
        return new self(
            id: $warehouse->id,
            location_id: $warehouse->location_id,
            location: $warehouse->location?->name,
            name: $warehouse->name,
            code: $warehouse->code,
            address: $warehouse->address,
            created_at: $warehouse->created_at->toISOString(),
        );
    }
}
