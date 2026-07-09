<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Resolves a validated `per_page` value for a paginated index. Shared by every
 * list controller so the allow-list and fallback stay consistent. A controller
 * can override $perPageOptions if it needs a different allow-list.
 */
trait ResolvesPerPage
{
    /** @var array<int, int> */
    protected array $perPageOptions = [10, 25, 50, 100];

    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', $this->perPageOptions[0]);

        return in_array($perPage, $this->perPageOptions, true)
            ? $perPage
            : $this->perPageOptions[0];
    }
}
