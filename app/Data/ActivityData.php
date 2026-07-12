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
    /** Friendly labels for the logged DB columns (fallback: humanized column name). */
    private const FIELD_LABELS = [
        'sku' => 'SKU',
        'bom' => 'BOM',
        'min_stock' => 'Reorder level',
        'category_id' => 'Category',
        'supplier_id' => 'Supplier',
        'customer_id' => 'Customer',
        'product_id' => 'Product',
        'raw_material_id' => 'Raw material',
        'warehouse_id' => 'Warehouse',
        'location_id' => 'Location',
        'unit_cost' => 'Unit cost',
        'unit_price' => 'Unit price',
        'counted_qty' => 'Counted',
        'system_qty' => 'Expected',
        'completed_warehouse_id' => 'Warehouse',
        'received_warehouse_id' => 'Warehouse',
        'fulfilled_warehouse_id' => 'Warehouse',
    ];

    /** Friendly labels for the record type (fallback: humanized class name). */
    private const SUBJECT_LABELS = [
        'BomItem' => 'BOM line',
        'WarehouseReorderLevel' => 'Reorder level',
        'RawMaterial' => 'Raw material',
    ];

    /** Technical columns not worth showing to the user. */
    private const SKIP_FIELDS = ['stockable_type', 'stockable_id', 'image', 'user_id'];

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

        $fields = array_values(array_diff(
            array_unique([...array_keys($new), ...array_keys($old)]),
            self::SKIP_FIELDS,
        ));

        $changes = array_map(fn (string $field): ActivityChangeData => new ActivityChangeData(
            field: self::FIELD_LABELS[$field] ?? Str::headline($field),
            old: self::stringify($old[$field] ?? null),
            new: self::stringify($new[$field] ?? null),
        ), $fields);

        $base = class_basename((string) $activity->subject_type);

        return new self(
            id: $activity->id,
            event: $activity->event,
            subject_type: self::SUBJECT_LABELS[$base] ?? Str::headline($base),
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
