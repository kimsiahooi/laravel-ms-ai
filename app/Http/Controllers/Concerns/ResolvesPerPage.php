<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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

    /**
     * Paginate a list query with the shared UI defaults, preserving the current
     * query string on the page links. The visible page-number window is now
     * computed client-side in the DataTable (a compact 2/3/2 window that stays
     * short at every position — Laravel's `onEachSide` can't shrink its edge run
     * below 4), so `onEachSide(1)` here only bounds the otherwise-unused `links`
     * payload. Ordering stays with the caller (add a deterministic
     * `latest()->latest('id')` there).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function paginateList(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query
            ->paginate($perPage)
            ->onEachSide(1)
            ->withQueryString();
    }
}
