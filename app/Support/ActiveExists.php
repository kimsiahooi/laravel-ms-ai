<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * A soft-delete-aware `exists` validation rule: the referenced row must exist AND not
 * be trashed. `Rule::exists()` bypasses the SoftDeletes global scope, so every FK
 * check would otherwise repeat `->whereNull('deleted_at')`; this centralises it.
 *
 *   'supplier_id' => ['required', ActiveExists::of('suppliers')]
 */
final class ActiveExists
{
    public static function of(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->whereNull('deleted_at');
    }
}
