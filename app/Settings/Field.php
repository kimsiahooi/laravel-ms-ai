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
            // The same limits the rules above enforce, in a shape the browser can
            // check before sending — so this form is validated from one definition
            // like every other, instead of only after a round trip.
            'constraints' => $this->constraints(),
        ];
    }

    /**
     * The machine-readable form of this field's rules.
     *
     * @return array{required: bool, numeric: bool, email: bool, max: float|null, min: float|null, scale: int|null, in: list<string>}
     */
    private function constraints(): array
    {
        $rules = array_values(array_filter($this->rules, 'is_string'));
        $has = fn (string $rule): bool => in_array($rule, $rules, true);

        return [
            'required' => $has('required'),
            'numeric' => $has('numeric') || $has('integer'),
            'email' => $has('email'),
            'max' => $this->ruleValue($rules, 'max'),
            'min' => $this->ruleValue($rules, 'min'),
            // `decimal:0,4` — the places the column keeps.
            'scale' => ($decimal = $this->ruleArgument($rules, 'decimal')) !== null
                ? (int) (explode(',', $decimal)[1] ?? 0)
                : null,
            'in' => ($in = $this->ruleArgument($rules, 'in')) !== null
                ? explode(',', $in)
                : [],
        ];
    }

    /**
     * @param  list<string>  $rules
     */
    private function ruleValue(array $rules, string $name): ?float
    {
        $argument = $this->ruleArgument($rules, $name);

        return $argument === null ? null : (float) $argument;
    }

    /**
     * The part after the colon in a `name:argument` rule.
     *
     * @param  list<string>  $rules
     */
    private function ruleArgument(array $rules, string $name): ?string
    {
        foreach ($rules as $rule) {
            if (str_starts_with($rule, $name.':')) {
                return substr($rule, strlen($name) + 1);
            }
        }

        return null;
    }
}
