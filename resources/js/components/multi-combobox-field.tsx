import type { ReactNode } from 'react';
import type { ComboboxOption } from '@/components/combobox';
import { FieldLabel } from '@/components/field-label';
import InputError from '@/components/input-error';
import { MultiCombobox } from '@/components/multi-combobox';

type MultiComboboxFieldProps = {
    id: string;
    label: string;
    options: ComboboxOption[];
    value: string[];
    onChange: (value: string[]) => void;
    error?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    /** Optional plain-language explanation shown in an info tooltip beside the label. */
    hint?: ReactNode;
};

/**
 * A labelled MultiCombobox form field with wired-up error display — the multi-select
 * counterpart to `ComboboxField`.
 */
export function MultiComboboxField({
    id,
    label,
    options,
    value,
    onChange,
    error,
    placeholder,
    searchPlaceholder,
    emptyText,
    hint,
}: MultiComboboxFieldProps) {
    const errorId = `${id}-error`;

    return (
        <div className="space-y-2">
            <FieldLabel htmlFor={id} hint={hint}>
                {label}
            </FieldLabel>
            <MultiCombobox
                id={id}
                options={options}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                searchPlaceholder={searchPlaceholder}
                emptyText={emptyText}
                invalid={!!error}
                describedBy={error ? errorId : undefined}
            />
            <InputError id={errorId} role="alert" message={error} />
        </div>
    );
}
