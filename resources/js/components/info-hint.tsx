import { Info } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * A standalone info (ⓘ) icon + tooltip for explaining a term inline — e.g. beside
 * a table header — where FieldLabel (which wraps a form Label) doesn't fit. The
 * icon is kept out of the tab order so keyboard navigation skips it.
 */
export function InfoHint({ children }: { children: ReactNode }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    tabIndex={-1}
                    aria-label="More information"
                    className="ml-1 inline-flex align-middle text-muted-foreground transition-colors hover:text-foreground focus-visible:text-foreground focus-visible:outline-none"
                >
                    <Info className="size-3.5" aria-hidden />
                </button>
            </TooltipTrigger>
            <TooltipContent className="max-w-xs font-normal">
                {children}
            </TooltipContent>
        </Tooltip>
    );
}
