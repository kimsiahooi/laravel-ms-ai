<?php

declare(strict_types=1);

namespace App\Settings;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Base for a group of settings (business, and future groups). Subclasses declare the
 * fields once; this class derives the frontend schema, the validation rules, the typed
 * current values, and the persistence — all from that single declaration.
 */
abstract class SettingsCategory
{
    /** The category slug used in the `settings` table + the route (e.g. 'business'). */
    abstract public function key(): string;

    /** @return list<Field> */
    abstract public function fields(): array;

    /**
     * Current stored values merged over each field's default and cast by type (numbers
     * to numeric, toggles to bool, multi to array, files to a has-file bool — the raw
     * stored path is never exposed here). Read-only.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $stored = Setting::valuesFor($this->key());

        $values = [];
        foreach ($this->fields() as $field) {
            if ($field->isFile()) {
                // The upload lives in the media table (the KV `value` stays null),
                // so a file field exposes only a has-file bool from media existence.
                $values[$field->key] = $this->fileMedia($field->key) !== null;

                continue;
            }

            $raw = $stored[$field->key] ?? null;
            $values[$field->key] = $this->cast($field, $raw);
        }

        return $values;
    }

    /**
     * The field schema for the dynamic renderer.
     *
     * @return list<array<string, mixed>>
     */
    public function schema(): array
    {
        return array_map(fn (Field $field): array => $field->toSchema(), $this->fields());
    }

    /**
     * Validation rules for the non-file values, keyed by field key.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->fields() as $field) {
            if (! $field->isFile()) {
                $rules[$field->key] = $field->rules;
            }
        }

        return $rules;
    }

    /**
     * Validation rules for file uploads + their remove flags, keyed for the controller.
     *
     * @return array<string, list<mixed>>
     */
    public function fileRules(): array
    {
        $rules = [];
        foreach ($this->fileFields() as $field) {
            $rules[$field->key] = $field->rules ?: ['nullable', 'image', 'max:2048'];
            $rules['remove_'.$field->key] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /** @return list<Field> */
    public function fileFields(): array
    {
        return array_values(array_filter($this->fields(), fn (Field $field): bool => $field->isFile()));
    }

    /** Whether $key is a declared file field — the only keys the file route may serve. */
    public function isFileField(string $key): bool
    {
        foreach ($this->fileFields() as $field) {
            if ($field->key === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Persist validated values, encoding by type. File fields are skipped here (they're
     * written by the controller after the upload is stored).
     *
     * @param  array<string, mixed>  $values
     */
    public function store(array $values): void
    {
        $encoded = [];
        foreach ($values as $key => $value) {
            $field = $this->field($key);
            if ($field === null || $field->isFile()) {
                continue;
            }
            $encoded[$key] = $this->encode($field, $value);
        }

        Setting::putMany($this->key(), $encoded);
    }

    /**
     * Seed a row for EVERY field so the settings table fully mirrors the schema —
     * fields with a default get it; nullable ones (TIN, legal name, …) are seeded
     * empty (null); a file field (logo) gets a null path (no upload yet). Additive:
     * `firstOrCreate` only inserts a missing (category, key) row and never overwrites a
     * stored / user-edited value, so re-running just backfills newly added fields.
     * Idempotent — safe on every provision and as a sync tool via `tenants:seed`.
     */
    public function seedDefaults(): void
    {
        foreach ($this->fields() as $field) {
            $value = $field->isFile() ? null : $this->encode($field, $field->default);

            Setting::firstOrCreate(
                ['category' => $this->key(), 'key' => $field->key],
                ['value' => $value],
            );
        }
    }

    /**
     * Media collection holding a file field's upload — one file per field row, so a
     * fixed name is fine (the (category, key) Setting row identifies the field).
     */
    private const FILE_COLLECTION = 'file';

    /** Store (replacing any existing) the uploaded file for a file field. */
    public function putFile(string $key, UploadedFile $file): void
    {
        $setting = Setting::firstOrCreate(
            ['category' => $this->key(), 'key' => $key],
            ['value' => null],
        );

        $setting->clearMediaCollection(self::FILE_COLLECTION);
        $setting->addMedia($file)->toMediaCollection(self::FILE_COLLECTION);
    }

    /** Remove the stored file for a file field, if any. */
    public function clearFile(string $key): void
    {
        $this->settingRow($key)?->clearMediaCollection(self::FILE_COLLECTION);
    }

    /** The stored media for a file field (read-only — never creates the row). */
    public function fileMedia(string $key): ?Media
    {
        return $this->settingRow($key)?->getFirstMedia(self::FILE_COLLECTION);
    }

    /** The existing (category, key) row, or null — never creates it. */
    private function settingRow(string $key): ?Setting
    {
        return Setting::query()
            ->where('category', $this->key())
            ->where('key', $key)
            ->first();
    }

    private function field(string $key): ?Field
    {
        foreach ($this->fields() as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Cast a stored raw string to its typed value. Only NON-file fields reach here:
     * a file field's upload lives in the media table (its `value` stays null), and
     * values() resolves it to a has-file bool before this is ever called.
     */
    private function cast(Field $field, ?string $raw): mixed
    {
        if ($raw === null) {
            return $field->default;
        }

        return match ($field->type) {
            FieldType::Number => is_numeric($raw) ? $raw + 0 : $field->default,
            FieldType::Toggle => filter_var($raw, FILTER_VALIDATE_BOOL),
            FieldType::MultiCombobox => is_array($decoded = json_decode($raw, true)) ? $decoded : [],
            default => $raw,
        };
    }

    private function encode(Field $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($field->type) {
            FieldType::MultiCombobox => json_encode(array_values((array) $value)),
            FieldType::Toggle => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
