import { Head } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Factory,
    ShoppingCart,
    TrendingUp,
    TriangleAlert,
} from 'lucide-react';
import { DateRangePicker } from '@/components/date-range-picker';
import { EmptyState } from '@/components/empty-state';
import { ExportMenu } from '@/components/export-menu';
import { InfoHint } from '@/components/info-hint';
import { SignedQuantity } from '@/components/signed-quantity';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useDateRangeFilter } from '@/hooks/use-date-range-filter';
import { usePageProps } from '@/hooks/use-page-props';
import TenantLayout from '@/layouts/tenant-layout';
import { formatQuantity } from '@/lib/format';
import { STOCK_STATUS_TEXT, stockStatus } from '@/lib/stock';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/tenant';
import reportsRoutes from '@/routes/tenant/reports';
import type { TenantPageProps } from '@/types';

type Movement = {
    reason: string;
    label: string;
    count: number;
    net_quantity: number;
};
type LowStock = {
    warehouse: string;
    item: string;
    unit: string;
    on_hand: number;
    reorder_level: number;
};

type PageProps = TenantPageProps & {
    filters: { from: string; to: string };
    sales: { count: number; quantity: number; amount: number };
    purchases: { count: number; quantity: number; amount: number };
    production: { count: number; quantity: number };
    movements: Movement[];
    lowStock: LowStock[];
};

export default function ReportsIndex() {
    const {
        filters,
        sales,
        purchases,
        production,
        movements,
        lowStock,
        tenant,
    } = usePageProps<PageProps>();
    const base = reportsRoutes.index.url({ tenant: tenant.slug });
    // Scope the reload to the period-dependent props (the closures the controller now
    // exposes) instead of refetching every prop on the page — the B4 over-fetch fix.
    const applyRange = useDateRangeFilter(base, [
        'filters',
        'sales',
        'purchases',
        'production',
        'movements',
        'lowStock',
    ]);

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: 'Reports', href: base },
            ]}
        >
            <Head title="Reports" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        Reports
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        A summary of activity over a period. Totals come from
                        each order's line amounts (quantity × price).
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <DateRangePicker
                        value={{ from: filters.from, to: filters.to }}
                        onChange={applyRange}
                    />
                    <ExportMenu
                        resource="reports"
                        params={{ from: filters.from, to: filters.to }}
                    />
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard
                    icon={TrendingUp}
                    label="Sales"
                    value={formatQuantity(sales.amount)}
                    sub={`${sales.count} orders · ${formatQuantity(sales.quantity)} sold`}
                />
                <StatCard
                    icon={ShoppingCart}
                    label="Purchases"
                    value={formatQuantity(purchases.amount)}
                    sub={`${purchases.count} received · ${formatQuantity(purchases.quantity)} in`}
                />
                <StatCard
                    icon={Factory}
                    label="Production"
                    value={formatQuantity(production.quantity)}
                    sub={`${production.count} builds completed`}
                />
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Card>
                    <CardContent className="p-0">
                        <div className="flex items-center gap-2 border-b p-4">
                            <ArrowLeftRight className="size-4 text-muted-foreground" />
                            <h2 className="font-medium text-sm">
                                Stock movements
                            </h2>
                        </div>
                        {movements.length === 0 ? (
                            <p className="p-6 text-center text-muted-foreground text-sm">
                                No stock movements in this period.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Reason</TableHead>
                                        <TableHead className="text-right">
                                            Count
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Net change
                                            <InfoHint>
                                                How much stock went up or down
                                                in total over this period (in
                                                minus out).
                                            </InfoHint>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {movements.map((movement) => (
                                        <TableRow key={movement.reason}>
                                            <TableCell>
                                                {movement.label}
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {movement.count}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <SignedQuantity
                                                    value={
                                                        movement.net_quantity
                                                    }
                                                    className="font-medium"
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <div className="flex items-center gap-2 border-b p-4">
                            <TriangleAlert className="size-4 text-muted-foreground" />
                            <h2 className="font-medium text-sm">
                                Low / out of stock
                            </h2>
                        </div>
                        {lowStock.length === 0 ? (
                            <EmptyState
                                icon={TriangleAlert}
                                title="Everything's above its reorder level"
                                description="No items are low or out of stock right now."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Item</TableHead>
                                        <TableHead className="text-right">
                                            On hand
                                            <InfoHint>
                                                The amount you have in stock
                                                right now.
                                            </InfoHint>
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Reorder at
                                            <InfoHint>
                                                When stock drops to this level,
                                                it's time to buy or make more.
                                            </InfoHint>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {lowStock.map((row) => {
                                        const status = stockStatus(
                                            row.on_hand,
                                            row.reorder_level,
                                        );
                                        return (
                                            <TableRow
                                                key={`${row.warehouse}-${row.item}`}
                                            >
                                                <TableCell>
                                                    <span className="font-medium text-foreground">
                                                        {row.item}
                                                    </span>
                                                    <span className="block text-muted-foreground text-xs">
                                                        {row.warehouse}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <span
                                                        className={cn(
                                                            'font-medium tabular-nums',
                                                            STOCK_STATUS_TEXT[
                                                                status.key
                                                            ],
                                                        )}
                                                    >
                                                        {formatQuantity(
                                                            row.on_hand,
                                                        )}{' '}
                                                        {row.unit}
                                                    </span>
                                                    <Badge
                                                        variant="secondary"
                                                        className="ml-2 align-middle"
                                                    >
                                                        {status.label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right text-muted-foreground tabular-nums">
                                                    {formatQuantity(
                                                        row.reorder_level,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </TenantLayout>
    );
}
