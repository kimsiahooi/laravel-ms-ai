<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One changed field on an activity: its label and the old → new values. */
#[TypeScript]
class ActivityChangeData extends Data
{
    public function __construct(
        public string $field,
        public ?string $old,
        public ?string $new,
    ) {}
}
