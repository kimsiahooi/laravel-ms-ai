import { Combobox, type ComboboxOption } from '@/components/combobox';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

type ComboboxFieldProps = {
    id: string;
    label: string;
    options: ComboboxOption[];
    value: string;
    onChange: (value: string) => void;
    error?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
};

/**
 * A labelled Combobox form field with wired-up error display — the reusable
 * shape for nullable foreign-key pickers (category, supplier, and future
 * order/BOM pickers).
 */
export function ComboboxField({
    id,
    label,
    options,
    value,
    onChange,
    error,
    placeholder,
    searchPlaceholder,
    emptyText,
}: ComboboxFieldProps) {
    const errorId = `${id}-error`;

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <Combobox
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
