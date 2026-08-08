<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One row of the e-invoice readiness checklist. */
#[TypeScript]
class EInvoiceCheckData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $passed,
        /** What to do about it, shown when the check fails. */
        public string $hint,
    ) {}
}
