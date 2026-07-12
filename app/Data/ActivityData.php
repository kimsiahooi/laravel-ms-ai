<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One row of the Activity history: who did what to which record, and when. */
#[TypeScript]
class ActivityData extends Data
{
    /**
     * @param  array<int, ActivityChangeData>  $changes
     */
    public function __construct(
        public int $id,
        /** created | updated | deleted */
        public ?string $event,
        /** Human label of the record's type, e.g. "Purchase Order". */
        public string $subject_type,
        /** The record's name when known, else "#id". */
        public string $subject,
        public ?string $causer,
        #[DataCollectionOf(ActivityChangeData::class)]
        public array $changes,
        public string $created_at,
    ) {}

    public static function fromActivity(Activity $activity): self
    {
        $changed = $activity->attribute_changes?->toArray() ?? [];
        $new = $changed['attributes'] ?? [];
        $old = $changed['old'] ?? [];

        // The subject may be soft-deleted or gone; fall back to its logged name.
        $name = $activity->subject?->name ?? ($new['name'] ?? $old['name'] ?? null);

        $fields = array_values(array_unique([...array_keys($new), ...array_keys($old)]));

        $changes = array_map(fn (string $field): ActivityChangeData => new ActivityChangeData(
            field: Str::headline($field),
            old: self::stringify($old[$field] ?? null),
            new: self::stringify($new[$field] ?? null),
        ), $fields);

        return new self(
            id: $activity->id,
            event: $activity->event,
            subject_type: Str::headline(class_basename((string) $activity->subject_type)),
            subject: $name !== null ? (string) $name : '#'.$activity->subject_id,
            causer: $activity->causer?->name,
            changes: $changes,
            created_at: $activity->created_at->toISOString(),
        );
    }

    private static function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => json_encode($value) ?: null,
            default => (string) $value,
        };
    }
}
