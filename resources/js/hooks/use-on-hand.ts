import { useEffect, useState } from 'react';
import stockRoutes from '@/routes/tenant/stock';

type OnHand = App.Data.StockOnHandData;

/**
 * Fetch on-hand + unit + reorder level for the selected (warehouse, item) pair,
 * for the stock movement / transfer dialogs. Returns null until both are chosen.
 * A changed selection (or unmount) aborts the in-flight request, so a slow older
 * response can never overwrite a newer selection.
 */
export function useOnHand(
    tenantSlug: string,
    warehouseId: string,
    stockable: string,
): { data: OnHand | null; loading: boolean } {
    const [data, setData] = useState<OnHand | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!warehouseId || !stockable) {
            setData(null);
            setLoading(false);
            return;
        }

        let active = true;
        const controller = new AbortController();
        setLoading(true);

        fetch(
            stockRoutes.onHand.url(
                { tenant: tenantSlug },
                { query: { warehouse_id: warehouseId, stockable } },
            ),
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        )
            .then((response) =>
                response.ok ? (response.json() as Promise<OnHand>) : null,
            )
            .then((payload) => {
                if (active) {
                    setData(payload);
                    setLoading(false);
                }
            })
            .catch(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
            controller.abort();
        };
    }, [tenantSlug, warehouseId, stockable]);

    return { data, loading };
}
