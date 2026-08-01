<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders a standard tenant resource index: a `search`-scoped, latest-first, paginated
 * list mapped through a Data object, plus the shared `filters` prop. For the simple
 * CRUD screens (categories, suppliers, customers, locations, raw materials) whose
 * index is otherwise identical. Requires {@see ResolvesPerPage}.
 */
trait RendersResourceIndex
{
    use SortsResourceQuery;

    /**
     * @param  class-string<Model>  $model  the Eloquent model (must have a `search` scope)
     * @param  callable(Model): mixed  $toData  maps each row to its Data object
     * @param  array<int, string>  $sortable  real columns the UI may sort by (default: created_at desc)
     */
    protected function resourceIndex(
        Request $request,
        string $model,
        string $view,
        string $key,
        callable $toData,
        array $sortable = [],
        string $defaultSort = 'created_at',
        string $defaultDirection = 'desc',
    ): Response {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        $query = $model::query()->search($search);
        $sort = $this->applySort($query, $request, $sortable, $defaultSort, $defaultDirection);

        $rows = $this->paginateList($query, $perPage)->through($toData);

        return Inertia::render($view, [
            $key => $rows,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sort['sort'],
                'direction' => $sort['direction'],
            ],
        ]);
    }
}
