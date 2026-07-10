<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Supplier;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The supplier list-item payload. snake_case property names keep the serialized
 * JSON (and the generated TS) byte-identical to the previous hand-mapped array.
 * #[TypeScript] makes the transformer emit App.Data.SupplierData.
 */
#[TypeScript]
class SupplierData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $notes,
        public string $created_at,
    ) {}

    public static function fromSupplier(Supplier $supplier): self
    {
        return new self(
            id: $supplier->id,
            name: $supplier->name,
            email: $supplier->email,
            phone: $supplier->phone,
            address: $supplier->address,
            notes: $supplier->notes,
            created_at: $supplier->created_at->toISOString(),
        );
    }
}
