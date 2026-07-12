import { formatQuantity } from '@/lib/format';
import { STOCK_STATUS_TEXT, stockStatus } from '@/lib/stock';
import { cn } from '@/lib/utils';

type OnHand = App.Data.StockOnHandData;

/**
 * A muted "On hand: 42 pcs" line coloured by stock status (amber when low, red
 * when out), shown beside the quantity field in the stock movement / transfer
 * dialogs. Renders nothing until a warehouse + item are selected.
 */
export function OnHandHint({
    data,
    loading,
    label = 'On hand',
}: {
    data: OnHand | null;
    loading: boolean;
    label?: string;
}) {
    if (loading && !data) {
        return <p className="text-muted-foreground text-xs">Checking stock…</p>;
    }

    if (!data) {
        return null;
    }

    const status = stockStatus(data.on_hand, data.reorder_level);

    return (
        <p className="text-xs">
            <span className="text-muted-foreground">{label}: </span>
            <span
                className={cn(
                    'font-medium tabular-nums',
                    STOCK_STATUS_TEXT[status.key],
                )}
            >
                {formatQuantity(data.on_hand)} {data.unit}
            </span>
            {status.key !== 'ok' && (
                <span className={cn('ml-1', STOCK_STATUS_TEXT[status.key])}>
                    · {status.label}
                </span>
            )}
        </p>
    );
}
