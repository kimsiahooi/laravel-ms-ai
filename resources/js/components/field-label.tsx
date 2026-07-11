import { Info } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type FieldLabelProps = ComponentProps<typeof Label> & {
    /** Optional plain-language explanation shown in an info tooltip beside the label. */
    hint?: ReactNode;
};

/**
 * A form label with an optional info tooltip — the reusable shape for any field
 * that needs a plain-language explanation. Pass `hint` to reveal a muted (ⓘ)
 * icon after the label text; the tooltip opens on hover/tap. The icon is kept
 * OUT of the tab order (`tabIndex={-1}`) so Tab flows field→field and never
 * lands on the hint. Drop-in replacement for `Label`.
 */
export function FieldLabel({ hint, children, ...props }: FieldLabelProps) {
    return (
        <Label {...props}>
            {children}
            {hint ? (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            tabIndex={-1}
                            aria-label="More information"
                            className="text-muted-foreground transition-colors hover:text-foreground focus-visible:text-foreground focus-visible:outline-none"
                        >
                            <Info className="size-3.5" aria-hidden />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent className="max-w-xs font-normal">
                        {hint}
                    </TooltipContent>
                </Tooltip>
            ) : null}
        </Label>
    );
}
