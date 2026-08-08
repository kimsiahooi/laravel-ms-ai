<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/** UBL `cac:PostalAddress` — a structured party address. */
class EInvoiceAddressData extends Data
{
    public function __construct(
        public ?string $address_line,
        public ?string $city_name,
        public ?string $postal_zone,
        /** State / region code (UBL CountrySubentityCode). */
        public ?string $country_subentity_code,
        /** ISO 3166-1 alpha-2. */
        public ?string $country_identification_code,
    ) {}
}
