import { formatQuantity } from '@/lib/format';
import { signedQuantityClass } from '@/lib/stock';
import { cn } from '@/lib/utils';

/**
 * A signed quantity for movement / variance cells: a leading `+` when positive,
 * coloured green (in) / destructive (out) / neutral (zero), digit-grouped. Pass
 * `unit` to append it, `className` for cell layout (e.g. `text-right font-medium`).
 */
export function SignedQuantity({
    value,
    unit,
    className,
}: {
    value: number;
    unit?: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'tabular-nums',
                signedQuantityClass(value),
                className,
            )}
        >
            {value > 0 ? '+' : ''}
            {formatQuantity(value)}
            {unit ? ` ${unit}` : ''}
        </span>
    );
}
