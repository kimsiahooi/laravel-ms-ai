<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Helpers for normalizing numeric form input in a FormRequest's
 * `prepareForValidation()`.
 */
trait NormalizesNumericInput
{
    /**
     * Coerce a blank (`null` or `''`) numeric field to a default (0) before
     * validation, so an omitted or cleared field validates as the default
     * instead of failing a `required|numeric` rule.
     */
    protected function defaultBlankToZero(string $field): void
    {
        if (in_array($this->input($field), [null, ''], true)) {
            $this->merge([$field => 0]);
        }
    }
}
