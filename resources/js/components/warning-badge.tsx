import type { ComponentProps } from 'react';
import { Badge } from '@/components/ui/badge';
import { AMBER_WARNING } from '@/lib/stock';
import { cn } from '@/lib/utils';

/**
 * A secondary Badge tinted amber for "needs attention" states (reorder / low stock).
 * Forwards all Badge props (children, tabIndex, …); merge extra classes via className.
 */
export function WarningBadge({
    className,
    ...props
}: ComponentProps<typeof Badge>) {
    return (
        <Badge
            variant="secondary"
            className={cn(AMBER_WARNING, className)}
            {...props}
        />
    );
}
