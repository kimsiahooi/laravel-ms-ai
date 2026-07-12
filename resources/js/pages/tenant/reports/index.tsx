import { Head, router } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Factory,
    ShoppingCart,
    TrendingUp,
    TriangleAlert,
} from 'lucide-react';
import {
    DateRangePicker,
    type DateRangeValue,
} from '@/components/date-range-picker';
import { EmptyState } from '@/components/empty-state';
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

function StatCard({
    icon: Icon,
    label,
    value,
    hint,
}: {
    icon: typeof ShoppingCart;
    label: string;
    value: string;
    hint: string;
}) {
    return (
        <Card>
            <CardContent className="flex items-start gap-3 p-5">
                <span className="grid size-9 shrink-0 place-items-center rounded-md bg-secondary text-muted-foreground">
                    <Icon className="size-4" />
                </span>
                <div className="min-w-0">
                    <p className="truncate font-semibold text-2xl tabular-nums">
                        {value}
                    </p>
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <p className="text-muted-foreground text-xs">{hint}</p>
                </div>
            </CardContent>
        </Card>
    );
}

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

    const applyRange = (range: DateRangeValue) => {
        router.get(
            base,
            { from: range.from, to: range.to },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

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
                        A summary of activity over a period. Amounts are
                        order-line totals.
                    </p>
                </div>
                <DateRangePicker
                    value={{ from: filters.from, to: filters.to }}
                    onChange={applyRange}
                />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard
                    icon={TrendingUp}
                    label="Sales"
                    value={formatQuantity(sales.amount)}
                    hint={`${sales.count} orders · ${formatQuantity(sales.quantity)} sold`}
                />
                <StatCard
                    icon={ShoppingCart}
                    label="Purchases"
                    value={formatQuantity(purchases.amount)}
                    hint={`${purchases.count} received · ${formatQuantity(purchases.quantity)} in`}
                />
                <StatCard
                    icon={Factory}
                    label="Production"
                    value={formatQuantity(production.quantity)}
                    hint={`${production.count} builds completed`}
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
                                            Net qty
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
                                            <TableCell
                                                className={cn(
                                                    'text-right font-medium tabular-nums',
                                                    movement.net_quantity < 0 &&
                                                        'text-destructive',
                                                    movement.net_quantity > 0 &&
                                                        'text-emerald-600 dark:text-emerald-400',
                                                )}
                                            >
                                                {movement.net_quantity > 0
                                                    ? '+'
                                                    : ''}
                                                {formatQuantity(
                                                    movement.net_quantity,
                                                )}
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
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Reorder at
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
