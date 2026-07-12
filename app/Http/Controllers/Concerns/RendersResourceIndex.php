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
    /**
     * @param  class-string<Model>  $model  the Eloquent model (must have a `search` scope)
     * @param  callable(Model): mixed  $toData  maps each row to its Data object
     */
    protected function resourceIndex(
        Request $request,
        string $model,
        string $view,
        string $key,
        callable $toData,
    ): Response {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        $rows = $model::query()
            ->search($search)
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through($toData);

        return Inertia::render($view, [
            $key => $rows,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }
}
