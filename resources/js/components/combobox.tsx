import { Check } from 'lucide-react';
import { ComboboxBase } from '@/components/combobox-base';
import { CommandGroup, CommandItem } from '@/components/ui/command';
import { cn } from '@/lib/utils';

export type ComboboxOption = { value: string; label: string };

type ComboboxProps = {
    options: ComboboxOption[];
    value: string;
    onChange: (value: string) => void;
    id?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    noneLabel?: string;
    /** Render the "None" (clear) choice. Off for required fields. Default on. */
    allowNone?: boolean;
    invalid?: boolean;
    describedBy?: string;
};

/**
 * Single-select searchable picker for nullable foreign keys — the classic shadcn
 * "combobox" shape. Renders through the shared `ComboboxBase` (Popover + Command);
 * this file owns only the single-select semantics (a "None" row, one active value,
 * close-on-select). The multi-select `MultiCombobox` shares the same base, so the
 * two can never drift.
 */
export function Combobox({
    options,
    value,
    onChange,
    id,
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    emptyText = 'No results.',
    noneLabel = 'None',
    allowNone = true,
    invalid,
    describedBy,
}: ComboboxProps) {
    const selected = options.find((option) => option.value === value);

    return (
        <ComboboxBase
            id={id}
            invalid={invalid}
            describedBy={describedBy}
            searchPlaceholder={searchPlaceholder}
            emptyText={emptyText}
            trigger={
                <span className={cn(!selected && 'text-muted-foreground')}>
                    {selected ? selected.label : placeholder}
                </span>
            }
        >
            {({ close }) => (
                <CommandGroup>
                    {allowNone ? (
                        <CommandItem
                            value="__none__"
                            onSelect={() => {
                                onChange('');
                                close();
                            }}
                        >
                            <Check
                                className={cn(
                                    'size-4',
                                    value === '' ? 'opacity-100' : 'opacity-0',
                                )}
                            />
                            <span className="text-muted-foreground">
                                {noneLabel}
                            </span>
                        </CommandItem>
                    ) : null}
                    {options.map((option) => (
                        <CommandItem
                            key={option.value}
                            value={option.label}
                            onSelect={() => {
                                onChange(option.value);
                                close();
                            }}
                        >
                            <Check
                                className={cn(
                                    'size-4',
                                    value === option.value
                                        ? 'opacity-100'
                                        : 'opacity-0',
                                )}
                            />
                            {option.label}
                        </CommandItem>
                    ))}
                </CommandGroup>
            )}
        </ComboboxBase>
    );
}
