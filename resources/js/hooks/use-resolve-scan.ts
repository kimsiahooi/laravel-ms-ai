import { useCallback } from 'react';
import stockRoutes from '@/routes/tenant/stock';

type Match = App.Data.StockItemMatchData;

/**
 * Resolve a scanned code (barcode / QR / SKU) to a stock item via the
 * `stock.resolve-item` endpoint. Returns the match, or null when nothing matches
 * (the endpoint 404s) or the request fails — callers show a "not found" toast.
 * Imperative (call `resolve(code)` on a scan), unlike the reactive `useOnHand`.
 */
export function useResolveScan(tenantSlug: string): {
    resolve: (code: string) => Promise<Match | null>;
} {
    const resolve = useCallback(
        async (code: string): Promise<Match | null> => {
            const trimmed = code.trim();
            if (!trimmed) {
                return null;
            }

            try {
                const response = await fetch(
                    stockRoutes.resolveItem.url(
                        { tenant: tenantSlug },
                        { query: { code: trimmed } },
                    ),
                    { headers: { Accept: 'application/json' } },
                );

                return response.ok ? ((await response.json()) as Match) : null;
            } catch {
                return null;
            }
        },
        [tenantSlug],
    );

    return { resolve };
}
