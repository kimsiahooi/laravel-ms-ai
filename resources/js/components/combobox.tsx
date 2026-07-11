import { Check, ChevronsUpDown } from 'lucide-react';
import { Popover as PopoverPrimitive } from 'radix-ui';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
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

/**
 * Single-select searchable picker for nullable foreign keys — the classic
 * shadcn "combobox" (Popover + Command) shape: a button showing the selection,
 * with a search box inside the dropdown. Radix Popover is composed directly
 * (rather than via ui/popover.tsx) so we control the portal target — no ui/ edits.
 *
 * Portal target: when the combobox is inside a Dialog, the dropdown is portaled
 * into that Dialog's content node, NOT the body. Two reasons:
 *  - body-portaled content lands outside the Dialog's focus trap, so its search
 *    input can't hold focus (the bug the old combobox had); inside the trap it can.
 *  - non-portaled content renders inside the field's `space-y` wrapper, whose
 *    spacing grows by the gap when the dropdown mounts, nudging the centered
 *    Dialog upward. Portaling to the Dialog root avoids that.
 * Outside a Dialog, `container` is undefined and Radix defaults to the body.
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
    invalid,
    describedBy,
}: ComboboxProps) {
    const [open, setOpen] = useState(false);
    const [container, setContainer] = useState<HTMLElement | null>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const selected = options.find((option) => option.value === value);

    const handleOpenChange = (next: boolean) => {
        if (next) {
            // Portal into the nearest Dialog content (if any) so the dropdown is
            // inside the focus trap and out of the field's spacing flow.
            setContainer(
                triggerRef.current?.closest<HTMLElement>(
                    '[data-slot="dialog-content"],[data-slot="sheet-content"]',
                ) ?? null,
            );
        }
        setOpen(next);
    };

    const select = (next: string) => {
        onChange(next);
        setOpen(false);
    };

    return (
        <PopoverPrimitive.Root open={open} onOpenChange={handleOpenChange}>
            <PopoverPrimitive.Trigger asChild>
                <Button
                    ref={triggerRef}
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
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
            </PopoverPrimitive.Trigger>
            <PopoverPrimitive.Portal container={container ?? undefined}>
                <PopoverPrimitive.Content
                    data-slot="popover-content"
                    align="start"
                    sideOffset={4}
                    collisionPadding={8}
                    className="data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 z-50 w-(--radix-popover-trigger-width) origin-(--radix-popover-content-transform-origin) rounded-md border bg-popover p-0 text-popover-foreground shadow-md outline-hidden data-[state=closed]:animate-out data-[state=open]:animate-in"
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
                </PopoverPrimitive.Content>
            </PopoverPrimitive.Portal>
        </PopoverPrimitive.Root>
    );
}
