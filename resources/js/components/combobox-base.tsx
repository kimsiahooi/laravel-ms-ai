import { ChevronsUpDown } from 'lucide-react';
import { Popover as PopoverPrimitive } from 'radix-ui';
import { type ReactNode, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandInput,
    CommandList,
} from '@/components/ui/command';

type ComboboxBaseProps = {
    id?: string;
    /** Trigger content — the single-select label, or the multi-select chips. */
    trigger: ReactNode;
    /** The command items; `close` collapses the popover (single-select calls it). */
    children: (helpers: { close: () => void }) => ReactNode;
    searchPlaceholder?: string;
    emptyText?: string;
    invalid?: boolean;
    describedBy?: string;
};

/**
 * The shared shell for the searchable pickers — the classic shadcn "combobox"
 * (Popover + Command): a trigger button, a search box, and a scrollable list. Both
 * the single-select `Combobox` and the multi-select `MultiCombobox` render through
 * this ONE component, so the sync-critical bits (portal target, styling, keyboard,
 * a11y) live in a single place and the two pickers can never drift apart. Callers
 * supply the trigger content and the command items; selection semantics stay theirs.
 *
 * Portal target: inside a Dialog/Sheet, the dropdown is portaled into that content
 * node (not the body) so its search input stays within the focus trap and the field's
 * spacing doesn't shift. Outside, Radix defaults to the body.
 */
export function ComboboxBase({
    id,
    trigger,
    children,
    searchPlaceholder = 'Search…',
    emptyText = 'No results.',
    invalid,
    describedBy,
}: ComboboxBaseProps) {
    const [open, setOpen] = useState(false);
    const [container, setContainer] = useState<HTMLElement | null>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);

    const handleOpenChange = (next: boolean) => {
        if (next) {
            setContainer(
                triggerRef.current?.closest<HTMLElement>(
                    '[data-slot="dialog-content"],[data-slot="sheet-content"]',
                ) ?? null,
            );
        }
        setOpen(next);
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
                    className="h-auto min-h-9 w-full justify-between font-normal"
                >
                    {trigger}
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
                            {children({ close: () => setOpen(false) })}
                        </CommandList>
                    </Command>
                </PopoverPrimitive.Content>
            </PopoverPrimitive.Portal>
        </PopoverPrimitive.Root>
    );
}
