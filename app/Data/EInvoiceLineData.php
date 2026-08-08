<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * One `cac:InvoiceLine`. Carries both `item_name` and `item_description` because
 * SG splits them (Name mandatory, Description optional) while MY has only
 * Description — emitting both keeps the payload lossless for either target.
 */
class EInvoiceLineData extends Data
{
    public function __construct(
        public string $id,
        public string $item_name,
        public string $item_description,
        public float $invoiced_quantity,
        /**
         * The product's unit of measure as captured in the catalog — a free-text
         * unit, NOT a validated UN/ECE Rec 20 code. The mapping layer normalises it.
         */
        public ?string $unit_code,
        public float $price_amount,
        /** Quantity × unit price, before tax (UBL LineExtensionAmount). */
        public float $line_extension_amount,
        /** Tax category: MY tax-type code (01 / E / 06) or SG GST code (SR / ZR / NG). */
        public string $tax_category_code,
        public float $tax_percent,
        public float $tax_amount,
    ) {}
}
