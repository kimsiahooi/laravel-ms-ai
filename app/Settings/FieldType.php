<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * The input type of a settings field. Each maps to a shadcn input in the dynamic
 * settings form (resources/js/components/settings/settings-form.tsx) and to a value
 * encoding in SettingsCategory. Add a case here + a branch in the renderer to grow
 * the framework.
 */
enum FieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Textarea = 'textarea';
    case Number = 'number';
    case Combobox = 'combobox';         // single searchable select
    case MultiCombobox = 'multicombobox'; // searchable multi-select (array value)
    case Toggle = 'toggle';             // boolean
    case File = 'file';                 // upload (e.g. logo)
}
