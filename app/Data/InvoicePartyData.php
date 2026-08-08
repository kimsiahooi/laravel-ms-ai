<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Customer;
use App\Settings\BusinessSettings;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A party (buyer or seller) on an invoice — tax identity + structured address. */
#[TypeScript]
class InvoicePartyData extends Data
{
    public function __construct(
        public string $name,
        /** Contact number — MyInvois marks it mandatory on both parties. */
        public ?string $phone,
        public ?string $tin,
        public ?string $registration_no,
        public ?string $sst_registration_no,
        public ?string $address,
        public ?string $city,
        public ?string $postcode,
        public ?string $state_code,
        public ?string $country_code,
    ) {}

    /**
     * The seller party, from the tenant's business settings. `tax_registration_no`
     * holds the SST (MY) or GST (SG) number, whichever the tenant charges.
     *
     * `legal_name` is documented as optional ("leave blank to use the workspace
     * name"), so it falls back to the tenant name exactly as the printed document
     * header does — the seller name is mandatory in both MY and SG.
     */
    public static function fromSettings(BusinessSettings $settings): self
    {
        $values = $settings->values();
        $legalName = trim((string) ($values['legal_name'] ?? ''));

        return new self(
            name: $legalName !== '' ? $legalName : (string) (tenant('name') ?? ''),
            phone: $values['phone'] ?? null,
            tin: $values['tin'] ?? null,
            registration_no: $values['registration_no'] ?? null,
            sst_registration_no: $values['tax_registration_no'] ?? null,
            address: $values['address'] ?? null,
            city: $values['city'] ?? null,
            postcode: $values['postcode'] ?? null,
            state_code: $values['state_code'] ?? null,
            country_code: $values['country'] ?? null,
        );
    }

    public static function fromCustomer(Customer $customer): self
    {
        return new self(
            name: $customer->name,
            phone: $customer->phone,
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
