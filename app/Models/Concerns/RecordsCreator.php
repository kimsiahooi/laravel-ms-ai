<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stamps the authenticated tenant user onto `created_by` when a record is first
 * created, and exposes a `creator` relation for "Created by" columns. Nullable by
 * design: rows created outside a request (seeders, jobs) or before this existed
 * simply have no creator.
 *
 * The consuming model needs a nullable `created_by` column. `created_by` is set
 * directly (not mass-assigned), so it does not belong in the model's Fillable.
 */
trait RecordsCreator
{
    public static function bootRecordsCreator(): void
    {
        static::creating(function (Model $model): void {
            $userId = auth()->id();

            if (is_int($userId) && $model->getAttribute('created_by') === null) {
                $model->setAttribute('created_by', $userId);
            }
        });
    }

    /**
     * The tenant user who created this record (null for system/seeded rows).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
