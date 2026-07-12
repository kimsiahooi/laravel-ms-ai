import { Badge } from '@/components/ui/badge';
import { statusVariant } from '@/lib/status';

/**
 * A status pill: the backend's human `status_label` coloured by its raw `status` via
 * {@link statusVariant}. Replaces the per-page statusVariant() copies + inline
 * ternaries on order / return / stock-take rows.
 */
export function StatusBadge({
    status,
    label,
    className,
}: {
    status: string;
    label: string;
    className?: string;
}) {
    return (
        <Badge variant={statusVariant(status)} className={className}>
            {label}
        </Badge>
    );
}
