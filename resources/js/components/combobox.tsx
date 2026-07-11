import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
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
    invalid?: boolean;
    describedBy?: string;
};

export function Combobox({
    options,
    value,
    onChange,
    id,
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    emptyText = 'No results.',
    noneLabel = 'None',
    invalid,
    describedBy,
}: ComboboxProps) {
    const [open, setOpen] = useState(false);
    const selected = options.find((option) => option.value === value);

    const select = (next: string) => {
        onChange(next);
        setOpen(false);
    };

    // The content renders inside the trigger's tree (portal={false} below), not at
    // the body — so when this combobox sits inside a Dialog, it stays within the
    // Dialog's focus scope and its search input can hold focus (a portaled popover
    // lands outside the trap and the search bar becomes unclickable). Being inside
    // the Dialog's pointer-events:auto region also keeps options clickable without
    // `modal` — a modal popover here would re-introduce the focus fight.
    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    aria-haspopup="listbox"
                    aria-expanded={open}
                    aria-invalid={invalid}
                    aria-describedby={describedBy}
                    className="w-full justify-between font-normal"
                >
                    <span className={cn(!selected && 'text-muted-foreground')}>
                        {selected ? selected.label : placeholder}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                portal={false}
                className="w-(--radix-popover-trigger-width) p-0"
            >
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        <CommandGroup>
                            <CommandItem
                                value="__none__"
                                onSelect={() => select('')}
                            >
                                <Check
                                    className={cn(
                                        'size-4',
                                        value === ''
                                            ? 'opacity-100'
                                            : 'opacity-0',
                                    )}
                                />
                                <span className="text-muted-foreground">
                                    {noneLabel}
                                </span>
                            </CommandItem>
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={option.label}
                                    onSelect={() => select(option.value)}
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
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
