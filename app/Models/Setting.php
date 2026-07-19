<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A per-tenant key-value setting, grouped by category. Stores only values — field
 * metadata (type, options, validation) lives in code (App\Settings\*). Lives on the
 * default connection, which InitializeTenancyByPath has switched to the tenant DB.
 *
 * File-typed fields (e.g. the business logo) attach their upload to the field's own
 * row via medialibrary rather than storing a path in `value`.
 *
 * @property int $id
 * @property string $category
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['category', 'key', 'value'])]
class Setting extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * All stored values for a category as [key => value]. Read-only — never creates
     * rows (so document/share reads stay side-effect free).
     *
     * @return array<string, string|null>
     */
    public static function valuesFor(string $category): array
    {
        return static::query()
            ->where('category', $category)
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * Upsert each key in a category. The unique (category, key) index makes this
     * race-safe: a concurrent first-write hits a unique violation that updateOrCreate
     * (createOrFirst) resolves, rather than creating a duplicate.
     *
     * @param  array<string, string|null>  $values
     */
    public static function putMany(string $category, array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(
                ['category' => $category, 'key' => $key],
                ['value' => $value],
            );
        }
    }
}
