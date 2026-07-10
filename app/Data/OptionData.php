<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A `{ id, name }` option for a select/combobox (category, supplier, …). */
#[TypeScript]
class OptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
