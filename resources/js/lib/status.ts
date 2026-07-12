// Terminal-success status values across the order / return / stock-take domains. They
// don't collide (each domain has its own), so one set drives the badge colour for all.
const SUCCESS_STATUSES = new Set([
    'fulfilled',
    'received',
    'completed',
    'posted',
]);

/**
 * The shadcn Badge variant for an order/return/stock-take status: a terminal-success
 * state → `default` (brand), `cancelled` → `outline`, everything else → `secondary`.
 * Mirrors each PHP enum's `badgeVariant()`.
 */
export function statusVariant(
    status: string,
): 'default' | 'secondary' | 'outline' {
    if (SUCCESS_STATUSES.has(status)) return 'default';
    if (status === 'cancelled') return 'outline';
    return 'secondary';
}
