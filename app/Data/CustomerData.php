<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Customer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The customer list-item payload. snake_case property names keep the serialized
 * JSON (and the generated TS) byte-identical to the previous hand-mapped array.
 * #[TypeScript] makes the transformer emit App.Data.CustomerData.
 */
#[TypeScript]
class CustomerData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $contact_person,
        public ?string $email,
        public ?string $tax_id,
        public ?string $phone,
        public ?string $address,
        public ?string $notes,
        public string $created_at,
    ) {}

    public static function fromCustomer(Customer $customer): self
    {
        return new self(
            id: $customer->id,
            name: $customer->name,
            contact_person: $customer->contact_person,
            email: $customer->email,
            tax_id: $customer->tax_id,
            phone: $customer->phone,
            address: $customer->address,
            notes: $customer->notes,
            created_at: $customer->created_at->toISOString(),
        );
    }
}
