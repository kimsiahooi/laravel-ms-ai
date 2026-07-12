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
     * Paginate a list query with the shared UI defaults: a compact 2-sibling
     * page window (`onEachSide(2)` — shorter than Laravel's default of 3) and
     * the current query string preserved on the page links. Ordering stays with
     * the caller (add a deterministic `latest()->latest('id')` there).
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
            ->onEachSide(2)
            ->withQueryString();
    }
}
