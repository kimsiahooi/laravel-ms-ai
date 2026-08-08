<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * One identifier plus the scheme that qualifies it — UBL's `ID[@schemeID]`.
 * MY schemes: TIN, BRN, NRIC, PASSPORT, ARMY, SST, TTX. SG uses `0195` (UEN)
 * for the Peppol endpoint. Values stay strings: `01` is not `1`.
 */
class EInvoiceIdentificationData extends Data
{
    public function __construct(
        public string $scheme_id,
        public string $id,
    ) {}
}
