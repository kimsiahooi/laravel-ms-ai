import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type NewResourceButtonProps = {
    /** Singular entity label, e.g. "Purchase order" — rendered as "New …". */
    label: string;
    onClick: () => void;
    /**
     * When set, the button is disabled and hovering reveals this reason. Use for
     * prerequisites the user hasn't met yet (e.g. "Add a supplier first").
     */
    disabledReason?: string;
    className?: string;
};

/**
 * The standard "New <entity>" primary action. When `disabledReason` is set it
 * renders disabled with a tooltip explaining why — a disabled `<button>` doesn't
 * emit hover events, so the wrapping span carries the tooltip trigger.
 */
export function NewResourceButton({
    label,
    onClick,
    disabledReason,
    className,
}: NewResourceButtonProps) {
    if (!disabledReason) {
        return (
            <Button onClick={onClick} className={className}>
                <Plus className="size-4" />
                New {label}
            </Button>
        );
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                {/* The disabled button can't emit hover events, so this wrapper
                    span carries the tooltip trigger for pointer users. */}
                <span className={cn('inline-flex', className)}>
                    <Button disabled className="pointer-events-none w-full">
                        <Plus className="size-4" />
                        New {label}
                    </Button>
                </span>
            </TooltipTrigger>
            <TooltipContent>{disabledReason}</TooltipContent>
        </Tooltip>
    );
}
