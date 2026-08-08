<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/** One `cac:TaxSubtotal` — the lines of a single tax category, summed. */
class EInvoiceTaxSubtotalData extends Data
{
    public function __construct(
        public string $tax_category_code,
        public float $taxable_amount,
        public float $tax_amount,
        public float $percent,
    ) {}
}
