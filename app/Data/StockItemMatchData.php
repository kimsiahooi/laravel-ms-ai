<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A scanned code resolved to a stock item, for the scan pickers. */
#[TypeScript]
class StockItemMatchData extends Data
{
    public function __construct(
        /** The merged picker key the flows accept, e.g. "product:5" / "raw_material:3". */
        public string $value,
        /** "{name} · Product" / "{name} · Raw material". */
        public string $label,
        public string $name,
        public ?string $sku,
        /** "product" | "raw_material". */
        public string $type,
    ) {}
}
