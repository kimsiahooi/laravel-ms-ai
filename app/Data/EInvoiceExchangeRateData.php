<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * UBL `cac:TaxExchangeRate` — the rate converting the invoice currency to the
 * tax-accounting currency. MyInvois expects this triplet for a non-MYR invoice.
 * Peppol has no exchange-rate term at all (it carries a second TaxTotal in the
 * tax currency instead), so on the SG side this is informational for the mapper.
 */
class EInvoiceExchangeRateData extends Data
{
    public function __construct(
        public string $source_currency_code,
        public string $target_currency_code,
        public float $calculation_rate,
    ) {}
}
