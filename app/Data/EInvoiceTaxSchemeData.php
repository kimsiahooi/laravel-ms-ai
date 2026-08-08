<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * UBL `cac:PartyTaxScheme` — a party's tax registration number and the scheme it
 * belongs to. MY uses `SST`; SG uses `GST` (not the `VAT` of the base Peppol
 * billing profile).
 */
class EInvoiceTaxSchemeData extends Data
{
    public function __construct(
        public string $company_id,
        public string $tax_scheme_id,
    ) {}
}
