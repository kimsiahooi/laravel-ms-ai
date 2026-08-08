<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * A party (supplier or customer) in the e-invoice payload, shaped after UBL 2.1
 * `cac:Party` — the common ancestor of both MyInvois and Peppol PINT SG.
 *
 * Identifications are a scheme/value list rather than fixed keys because that is
 * how both standards model them: MY emits several `PartyIdentification/ID` entries
 * distinguished by `schemeID` (TIN, then BRN), SG routes on `EndpointID` with
 * scheme `0195` (UEN). The SST/GST number is NOT one of them — it lives in
 * `party_tax_scheme`, matching UBL's separate `cac:PartyTaxScheme`.
 */
class EInvoicePartyData extends Data
{
    /**
     * @param  array<int, EInvoiceIdentificationData>  $party_identifications
     */
    public function __construct(
        /** UBL PartyLegalEntity/RegistrationName. */
        public string $registration_name,
        public array $party_identifications,
        /** Peppol network address (SG); null for MY, which has no endpoint concept. */
        public ?EInvoiceIdentificationData $endpoint_id,
        /** UBL PartyTaxScheme — the SST (MY) or GST (SG) registration. */
        public ?EInvoiceTaxSchemeData $party_tax_scheme,
        /** UBL Contact/Telephone — mandatory for MyInvois, so it must travel. */
        public ?string $telephone,
        public EInvoiceAddressData $postal_address,
    ) {}
}
