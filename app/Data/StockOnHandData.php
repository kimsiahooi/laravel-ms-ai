<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The on-hand lookup for a (warehouse, item) pair, shown beside the quantity
 * field in the stock movement / transfer dialogs. `reorder_level` is null when
 * none is set for the pair.
 */
#[TypeScript]
class StockOnHandData extends Data
{
    public function __construct(
        public float $on_hand,
        public string $unit,
        public ?float $reorder_level,
    ) {}
}
