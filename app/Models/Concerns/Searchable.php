<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a `search($term)` query scope that filters over the model's declared
 * `$searchable` columns with a grouped, case-insensitive LIKE. A blank term (or
 * no searchable columns) is a no-op, so it composes cleanly in an index query.
 *
 * A model may also declare `$searchableRelations` — a map of `relation => [columns]`
 * — to reach across a belongsTo (e.g. an order matching its supplier/customer name)
 * via `orWhereHas`. Both properties are optional and read null-safely.
 */
trait Searchable
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        /** @var array<int, string> $columns */
        $columns = $this->searchable ?? [];
        /** @var array<string, array<int, string>> $relations */
        $relations = $this->searchableRelations ?? [];

        if ($term === '' || (empty($columns) && empty($relations))) {
            return $query;
        }

        return $query->where(function (Builder $group) use ($term, $columns, $relations): void {
            foreach ($columns as $column) {
                $group->orWhere($column, 'like', "%{$term}%");
            }

            foreach ($relations as $relation => $relationColumns) {
                $group->orWhereHas($relation, function (Builder $related) use ($relationColumns, $term): void {
                    $related->where(function (Builder $inner) use ($relationColumns, $term): void {
                        foreach ($relationColumns as $column) {
                            $inner->orWhere($column, 'like', "%{$term}%");
                        }
                    });
                });
            }
        });
    }
}
