<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Customer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A party (buyer or seller) on an invoice — tax identity + structured address. */
#[TypeScript]
class InvoicePartyData extends Data
{
    public function __construct(
        public string $name,
        public ?string $tin,
        public ?string $registration_no,
        public ?string $sst_registration_no,
        public ?string $address,
        public ?string $city,
        public ?string $postcode,
        public ?string $state_code,
        public ?string $country_code,
    ) {}

    public static function fromCustomer(Customer $customer): self
    {
        return new self(
            name: $customer->name,
            tin: $customer->tin,
            registration_no: $customer->registration_no,
            sst_registration_no: $customer->sst_registration_no,
            address: $customer->address,
            city: $customer->city,
            postcode: $customer->postcode,
            state_code: $customer->state_code,
            country_code: $customer->country_code,
        );
    }
}
