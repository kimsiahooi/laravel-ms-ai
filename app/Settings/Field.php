<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * The code-defined metadata for one settings field — the single source of truth that
 * drives BOTH the dynamic form input and the generated validation, so they never
 * drift. The value itself lives in the `settings` table (category/key/value).
 */
class Field
{
    /**
     * @param  list<array{value: string, label: string}>  $options  choices for combobox / multicombobox
     * @param  list<mixed>  $rules  Laravel validation rules for this field's value
     */
    public function __construct(
        public readonly string $key,
        public readonly FieldType $type,
        public readonly string $label,
        public readonly string $section,
        public readonly mixed $default = null,
        public readonly ?string $description = null,
        public readonly array $options = [],
        public readonly array $rules = [],
        public readonly ?string $placeholder = null,
    ) {}

    public function isFile(): bool
    {
        return $this->type === FieldType::File;
    }

    /**
     * The shape sent to the frontend renderer (never includes the value — that comes
     * from the category's values(), and file paths are never exposed).
     *
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'label' => $this->label,
            'section' => $this->section,
            'description' => $this->description,
            'options' => $this->options,
            'placeholder' => $this->placeholder,
            // Lets the renderer drop the "None" choice on a required combobox.
            'required' => in_array('required', $this->rules, true),
        ];
    }
}
