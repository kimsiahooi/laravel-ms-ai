<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Location;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The location (site) list-item payload. snake_case property names keep the
 * serialized JSON (and the generated TS) aligned with the other catalog
 * resources. #[TypeScript] makes the transformer emit App.Data.LocationData.
 */
#[TypeScript]
class LocationData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $code,
        public ?string $address,
        public string $created_at,
    ) {}

    public static function fromLocation(Location $location): self
    {
        return new self(
            id: $location->id,
            name: $location->name,
            code: $location->code,
            address: $location->address,
            created_at: $location->created_at->toISOString(),
        );
    }
}
