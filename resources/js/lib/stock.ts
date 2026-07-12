export type StockStatusKey = 'ok' | 'low' | 'out';

export type StockStatus = {
    key: StockStatusKey;
    label: string;
};

/**
 * Derive the stock status of an on-hand quantity against its reorder level:
 * `out` at zero, `low` at or below the reorder level (when one is set), else
 * `ok`. Shared by the warehouse list, the warehouse detail page, and the stock
 * dialogs so the OK / Low / Out colours stay consistent everywhere.
 */
export function stockStatus(
    onHand: number,
    reorderLevel: number | null,
): StockStatus {
    if (onHand <= 0) {
        return { key: 'out', label: 'Out of stock' };
    }

    if (reorderLevel !== null && reorderLevel > 0 && onHand <= reorderLevel) {
        return { key: 'low', label: 'Low' };
    }

    return { key: 'ok', label: 'OK' };
}

/**
 * Text-colour classes per status. Amber (warning) has no design token, so it
 * uses the same explicit amber utilities used elsewhere in the stock screens;
 * `out` uses the `destructive` token; `ok` stays neutral.
 */
export const STOCK_STATUS_TEXT: Record<StockStatusKey, string> = {
    ok: 'text-muted-foreground',
    low: 'text-amber-600 dark:text-amber-400',
    out: 'text-destructive',
};
