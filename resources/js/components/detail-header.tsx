import type { ReactNode } from 'react';
import { StatusBadge } from '@/components/status-badge';

type DetailHeaderProps = {
    title: string;
    /** Drives the coloured lifecycle badge beside the title. */
    status: { status: string; label: string };
    /** Optional one-line subtitle, e.g. the party the document is with. */
    description?: ReactNode;
    /** Right-aligned action buttons (lifecycle actions, Print, …). */
    actions?: ReactNode;
};

/**
 * The header for an interactive detail page: a title with its lifecycle status
 * badge, an optional subtitle, and a row of actions. Stacks on narrow screens.
 */
export function DetailHeader({
    title,
    status,
    description,
    actions,
}: DetailHeaderProps) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="space-y-1">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {title}
                    </h1>
                    <StatusBadge status={status.status} label={status.label} />
                </div>
                {description ? (
                    <p className="text-muted-foreground text-sm">
                        {description}
                    </p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}
