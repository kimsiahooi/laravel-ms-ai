<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Applies a whitelisted `sort` + `direction` from the request to an index query,
 * with a deterministic `id` tiebreaker so equal sort values page consistently.
 * The allow-list is the set of real table columns a resource lets the UI sort by;
 * an unknown or missing `sort` falls back to the resource's default column, so a
 * hand-typed `?sort=` can never order by an arbitrary column.
 */
trait SortsResourceQuery
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $allowed
     * @return array{sort: string, direction: 'asc'|'desc'}
     */
    protected function applySort(
        Builder $query,
        Request $request,
        array $allowed,
        string $default = 'created_at',
        string $defaultDirection = 'desc',
    ): array {
        $sort = (string) $request->string('sort');
        if (! in_array($sort, $allowed, true)) {
            $sort = $default;
        }

        // Resolve to a literal 'asc'|'desc': an explicit request param wins, else
        // the resource's default direction (normalised).
        if ($request->filled('direction')) {
            $direction = strtolower((string) $request->string('direction')) === 'asc' ? 'asc' : 'desc';
        } else {
            $direction = strtolower($defaultDirection) === 'asc' ? 'asc' : 'desc';
        }

        $query->orderBy($sort, $direction);

        // Deterministic tiebreaker so rows with an equal sort value keep a stable
        // order across pages (otherwise pagination can repeat or drop rows).
        if ($sort !== 'id') {
            $query->orderBy('id', 'desc');
        }

        return ['sort' => $sort, 'direction' => $direction];
    }
}
