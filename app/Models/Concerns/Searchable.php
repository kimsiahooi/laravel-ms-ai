<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a `search($term)` query scope that filters over the model's declared
 * `$searchable` columns with a grouped, case-insensitive LIKE. A blank term (or
 * no $searchable columns) is a no-op, so it composes cleanly in an index query.
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

        if ($term === '' || empty($this->searchable)) {
            return $query;
        }

        return $query->where(function (Builder $group) use ($term): void {
            foreach ($this->searchable as $column) {
                $group->orWhere($column, 'like', "%{$term}%");
            }
        });
    }
}
