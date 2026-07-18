import { Check } from 'lucide-react';
import type { ComboboxOption } from '@/components/combobox';
import { ComboboxBase } from '@/components/combobox-base';
import { Badge } from '@/components/ui/badge';
import { CommandGroup, CommandItem } from '@/components/ui/command';
import { cn } from '@/lib/utils';

type MultiComboboxProps = {
    options: ComboboxOption[];
    value: string[];
    onChange: (value: string[]) => void;
    id?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    invalid?: boolean;
    describedBy?: string;
};

/**
 * Multi-select searchable picker. Shares the exact same `ComboboxBase` shell as the
 * single-select `Combobox` — this file owns only the multi-select semantics: an array
 * value, selected items shown as chips in the trigger, and toggle-without-closing so
 * several can be picked in one open.
 */
export function MultiCombobox({
    options,
    value,
    onChange,
    id,
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    emptyText = 'No results.',
    invalid,
    describedBy,
}: MultiComboboxProps) {
    const selected = options.filter((option) => value.includes(option.value));

    const toggle = (next: string) => {
        onChange(
            value.includes(next)
                ? value.filter((item) => item !== next)
                : [...value, next],
        );
    };

    return (
        <ComboboxBase
            id={id}
            invalid={invalid}
            describedBy={describedBy}
            searchPlaceholder={searchPlaceholder}
            emptyText={emptyText}
            trigger={
                selected.length === 0 ? (
                    <span className="text-muted-foreground">{placeholder}</span>
                ) : (
                    <span className="flex flex-wrap gap-1">
                        {selected.map((option) => (
                            <Badge key={option.value} variant="secondary">
                                {option.label}
                            </Badge>
                        ))}
                    </span>
                )
            }
        >
            {() => (
                <CommandGroup>
                    {options.map((option) => (
                        <CommandItem
                            key={option.value}
                            value={option.label}
                            onSelect={() => toggle(option.value)}
                        >
                            <Check
                                className={cn(
                                    'size-4',
                                    value.includes(option.value)
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
