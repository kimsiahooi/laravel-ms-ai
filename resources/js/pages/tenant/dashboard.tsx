import { Head, Link, router } from '@inertiajs/react';
import { Boxes, ClipboardList, Factory, PackageX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Label,
    Pie,
    PieChart,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartCard } from '@/components/chart-card';
import {
    DateRangePicker,
    type DateRangeValue,
    formatRangeDates,
    thisMonthRange,
} from '@/components/date-range-picker';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    type ChartConfig,
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
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
import { formatQuantity, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/tenant';
import products from '@/routes/tenant/products';
import rawMaterials from '@/routes/tenant/raw-materials';
import type { TenantBrand } from '@/types';

type Movement = App.Data.StockMovementData;

type TrendPoint = { date: string; label: string };
type ActivityPoint = TrendPoint & { in: number; out: number };
type ThroughputPoint = TrendPoint & { units: number };
type WarehouseBar = { name: string; quantity: number };
type ReorderRow = {
    type: 'product' | 'raw_material';
    id: number;
    name: string;
    sku: string;
    on_hand: number;
    min_stock: number;
    deficit: number;
};

type PageProps = {
    auth: { user: { name: string; email: string } | null };
    tenant: TenantBrand | null;
    kpis: {
        open_documents: {
            total: number;
            purchase: number;
            sales: number;
            production: number;
        };
        low_stock: { count: number; out_of_stock: number };
        production_in_progress: { pending: number };
        skus_in_stock: { count: number; products: number; materials: number };
    };
    range: { from: string; to: string; units_made: number };
    stockActivity: ActivityPoint[];
    orderPipeline: {
        purchase: Record<string, number>;
        sales: Record<string, number>;
        production: Record<string, number>;
    };
    throughput: ThroughputPoint[];
    onHandByWarehouse: WarehouseBar[];
    reorderList: ReorderRow[];
    recentMovements: Movement[];
};

const activityConfig = {
    in: { label: 'Units in', color: 'var(--chart-1)' },
    out: { label: 'Units out', color: 'var(--chart-2)' },
} satisfies ChartConfig;

const throughputConfig = {
    units: { label: 'Units made', color: 'var(--chart-1)' },
} satisfies ChartConfig;

const onHandConfig = {
    quantity: { label: 'On hand', color: 'var(--chart-1)' },
} satisfies ChartConfig;

// Status → colour. Pending is the brand indigo (the "active" state); the positive
// terminal state (received/fulfilled/completed) is teal; cancelled is muted.
const PENDING = 'var(--chart-1)';
const DONE = 'var(--chart-2)';
const CANCELLED = 'var(--muted-foreground)';

const pipelineConfigs = {
    purchase: {
        title: 'Purchase',
        doneKey: 'received',
        config: {
            pending: { label: 'Pending', color: PENDING },
            received: { label: 'Received', color: DONE },
            cancelled: { label: 'Cancelled', color: CANCELLED },
        } satisfies ChartConfig,
    },
    sales: {
        title: 'Sales',
        doneKey: 'fulfilled',
        config: {
            pending: { label: 'Pending', color: PENDING },
            fulfilled: { label: 'Fulfilled', color: DONE },
            cancelled: { label: 'Cancelled', color: CANCELLED },
        } satisfies ChartConfig,
    },
    production: {
        title: 'Production',
        doneKey: 'completed',
        config: {
            pending: { label: 'Pending', color: PENDING },
            completed: { label: 'Completed', color: DONE },
            cancelled: { label: 'Cancelled', color: CANCELLED },
        } satisfies ChartConfig,
    },
} as const;

function PipelineDonut({
    title,
    counts,
    config,
}: {
    title: string;
    counts: Record<string, number>;
    config: ChartConfig;
}) {
    const total = Object.values(counts).reduce((sum, n) => sum + n, 0);
    const pending = counts.pending ?? 0;
    const data =
        total === 0
            ? [{ name: 'empty', value: 1, fill: 'var(--muted)' }]
            : Object.entries(counts)
                  .filter(([, value]) => value > 0)
                  .map(([name, value]) => ({
                      name,
                      value,
                      fill: `var(--color-${name})`,
                  }));

    return (
        <div className="flex flex-col items-center gap-2">
            <ChartContainer
                config={config}
                className="aspect-square h-[132px]"
                role="img"
                aria-label={`${title} orders: ${pending} pending of ${total} total`}
            >
                <PieChart>
                    {total > 0 ? (
                        <ChartTooltip
                            cursor={false}
                            content={
                                <ChartTooltipContent nameKey="name" hideLabel />
                            }
                        />
                    ) : null}
                    <Pie
                        data={data}
                        dataKey="value"
                        nameKey="name"
                        innerRadius={38}
                        outerRadius={56}
                        strokeWidth={2}
                        isAnimationActive={false}
                    >
                        {data.map((slice) => (
                            <Cell key={slice.name} fill={slice.fill} />
                        ))}
                        <Label
                            content={({ viewBox }) => {
                                if (!viewBox || !('cx' in viewBox)) return null;
                                return (
                                    <text
                                        x={viewBox.cx}
                                        y={viewBox.cy}
                                        textAnchor="middle"
                                        dominantBaseline="middle"
                                    >
                                        <tspan
                                            x={viewBox.cx}
                                            dy="-0.2em"
                                            className="fill-foreground font-semibold text-lg tabular-nums"
                                        >
                                            {pending}
                                        </tspan>
                                        <tspan
                                            x={viewBox.cx}
                                            dy="1.4em"
                                            className="fill-muted-foreground text-[10px]"
                                        >
                                            pending
                                        </tspan>
                                    </text>
                                );
                            }}
                        />
                    </Pie>
                </PieChart>
            </ChartContainer>
            <span className="font-medium text-muted-foreground text-sm">
                {title}
            </span>
        </div>
    );
}

function LegendSwatch({ color, label }: { color: string; label: string }) {
    return (
        <span className="flex items-center gap-1.5 text-muted-foreground text-xs">
            <span
                className="size-2.5 rounded-[3px]"
                style={{ backgroundColor: color }}
            />
            {label}
        </span>
    );
}

export default function TenantDashboard() {
    const {
        auth,
        tenant,
        kpis,
        range,
        stockActivity,
        orderPipeline,
        throughput,
        onHandByWarehouse,
        reorderList,
        recentMovements,
    } = usePageProps<PageProps>();

    const [greeting, setGreeting] = useState('Welcome back');
    const firstName = auth.user?.name?.trim().split(/\s+/)[0] || 'there';

    const rangeSpan = formatRangeDates(range);

    // Apply a new range: reload only the range-driven widgets. `from`/`to` are
    // offset-carrying ISO datetimes (the user's own device clock + zone), so no
    // separate timezone needs to be sent.
    const applyRange = (next: DateRangeValue) => {
        router.get(
            dashboard.url({ tenant: tenant?.slug ?? '' }),
            { from: next.from, to: next.to },
            {
                only: ['range', 'stockActivity', 'throughput'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    // On a fresh visit (no explicit range in the URL), default to "This month"
    // resolved in the device's own timezone. The server's default is UTC-based;
    // this one-time reload makes the default device-accurate (12 AM → 11:59 PM).
    const bootstrapped = useRef(false);
    // biome-ignore lint/correctness/useExhaustiveDependencies: intentional one-time mount effect
    useEffect(() => {
        if (bootstrapped.current) return;
        bootstrapped.current = true;
        if (!new URLSearchParams(window.location.search).has('from')) {
            applyRange(thisMonthRange());
        }
    }, []);

    useEffect(() => {
        const hour = new Date().getHours();
        setGreeting(
            hour < 12
                ? 'Good morning'
                : hour < 18
                  ? 'Good afternoon'
                  : 'Good evening',
        );
    }, []);

    const hasActivity = stockActivity.some((d) => d.in > 0 || d.out > 0);
    const hasThroughput = throughput.some((d) => d.units > 0);
    const hasOrders = [
        orderPipeline.purchase,
        orderPipeline.sales,
        orderPipeline.production,
    ].some((counts) => Object.values(counts).some((n) => n > 0));

    const productsUrl = products.index.url({ tenant: tenant?.slug ?? '' });
    const materialsUrl = rawMaterials.index.url({ tenant: tenant?.slug ?? '' });

    return (
        <TenantLayout>
            <Head title={`Dashboard — ${tenant?.name ?? 'Workspace'}`} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        Dashboard
                    </h1>
                    <p
                        className="text-muted-foreground text-sm"
                        suppressHydrationWarning
                    >
                        {greeting}, {firstName}. Here's how{' '}
                        {tenant?.name ?? 'your workspace'} is doing.
                    </p>
                </div>
                <DateRangePicker
                    value={{ from: range.from, to: range.to }}
                    onChange={applyRange}
                />
            </div>

            {/* KPI row */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={ClipboardList}
                    label="Open orders"
                    value={kpis.open_documents.total}
                    sub={`${kpis.open_documents.purchase} buy · ${kpis.open_documents.sales} sell · ${kpis.open_documents.production} make`}
                />
                <StatCard
                    icon={PackageX}
                    label="Low-stock items"
                    value={kpis.low_stock.count}
                    valueClassName={
                        kpis.low_stock.count > 0
                            ? 'text-destructive'
                            : undefined
                    }
                    sub={
                        kpis.low_stock.out_of_stock > 0
                            ? `${kpis.low_stock.out_of_stock} out of stock`
                            : 'below reorder point'
                    }
                />
                <StatCard
                    icon={Factory}
                    label="Production in progress"
                    value={kpis.production_in_progress.pending}
                    sub={`${formatQuantity(range.units_made)} units made in range`}
                />
                <StatCard
                    icon={Boxes}
                    label="Items in stock"
                    value={kpis.skus_in_stock.count}
                    sub={`${kpis.skus_in_stock.products} products · ${kpis.skus_in_stock.materials} materials`}
                />
            </div>

            {/* Charts */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <ChartCard
                    title="Stock activity"
                    description={`Units moved in and out · ${rangeSpan}`}
                    isEmpty={!hasActivity}
                    emptyText="No stock movements in this range."
                >
                    <ChartContainer
                        config={activityConfig}
                        className="h-[232px] w-full"
                        role="img"
                        aria-label="Units moved in and out per day over the selected range"
                    >
                        <AreaChart
                            accessibilityLayer
                            data={stockActivity}
                            margin={{ left: -12, right: 8, top: 4 }}
                        >
                            <defs>
                                <linearGradient
                                    id="fillIn"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="5%"
                                        stopColor="var(--color-in)"
                                        stopOpacity={0.35}
                                    />
                                    <stop
                                        offset="95%"
                                        stopColor="var(--color-in)"
                                        stopOpacity={0.03}
                                    />
                                </linearGradient>
                                <linearGradient
                                    id="fillOut"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="5%"
                                        stopColor="var(--color-out)"
                                        stopOpacity={0.35}
                                    />
                                    <stop
                                        offset="95%"
                                        stopColor="var(--color-out)"
                                        stopOpacity={0.03}
                                    />
                                </linearGradient>
                            </defs>
                            <CartesianGrid
                                vertical={false}
                                strokeDasharray="3 3"
                            />
                            <XAxis
                                dataKey="label"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                minTickGap={28}
                                fontSize={11}
                            />
                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                width={40}
                                fontSize={11}
                                allowDecimals={false}
                            />
                            <ChartTooltip
                                content={
                                    <ChartTooltipContent indicator="line" />
                                }
                            />
                            <Area
                                dataKey="in"
                                type="monotone"
                                stroke="var(--color-in)"
                                strokeWidth={2}
                                fill="url(#fillIn)"
                            />
                            <Area
                                dataKey="out"
                                type="monotone"
                                stroke="var(--color-out)"
                                strokeWidth={2}
                                fill="url(#fillOut)"
                            />
                        </AreaChart>
                    </ChartContainer>
                    <div className="mt-3 flex items-center justify-center gap-4">
                        <LegendSwatch color="var(--chart-1)" label="In" />
                        <LegendSwatch color="var(--chart-2)" label="Out" />
                    </div>
                </ChartCard>

                <ChartCard
                    title="Production output"
                    description={`Finished units per day · ${rangeSpan}`}
                    isEmpty={!hasThroughput}
                    emptyText="No production completed in this range."
                >
                    <ChartContainer
                        config={throughputConfig}
                        className="h-[232px] w-full"
                        role="img"
                        aria-label="Finished units produced per day over the selected range"
                    >
                        <BarChart
                            accessibilityLayer
                            data={throughput}
                            margin={{ left: -12, right: 8, top: 4 }}
                        >
                            <CartesianGrid
                                vertical={false}
                                strokeDasharray="3 3"
                            />
                            <XAxis
                                dataKey="label"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                minTickGap={28}
                                fontSize={11}
                            />
                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                width={40}
                                fontSize={11}
                                allowDecimals={false}
                            />
                            <ChartTooltip
                                cursor={false}
                                content={<ChartTooltipContent />}
                            />
                            <Bar
                                dataKey="units"
                                fill="var(--color-units)"
                                radius={[4, 4, 0, 0]}
                            />
                        </BarChart>
                    </ChartContainer>
                </ChartCard>

                <ChartCard
                    title="Orders by status"
                    description="How many orders are still open vs finished"
                    isEmpty={!hasOrders}
                    emptyText="No orders yet."
                >
                    <div className="grid grid-cols-3 gap-2">
                        <PipelineDonut
                            title={pipelineConfigs.purchase.title}
                            counts={orderPipeline.purchase}
                            config={pipelineConfigs.purchase.config}
                        />
                        <PipelineDonut
                            title={pipelineConfigs.sales.title}
                            counts={orderPipeline.sales}
                            config={pipelineConfigs.sales.config}
                        />
                        <PipelineDonut
                            title={pipelineConfigs.production.title}
                            counts={orderPipeline.production}
                            config={pipelineConfigs.production.config}
                        />
                    </div>
                    <div className="mt-3 flex flex-wrap items-center justify-center gap-4">
                        <LegendSwatch color={PENDING} label="Pending" />
                        <LegendSwatch color={DONE} label="Completed" />
                        <LegendSwatch color={CANCELLED} label="Cancelled" />
                    </div>
                </ChartCard>

                <ChartCard
                    title="Stock by warehouse"
                    description="Total units currently in stock"
                    isEmpty={onHandByWarehouse.length === 0}
                    emptyText="No stock on hand yet."
                >
                    <ChartContainer
                        config={onHandConfig}
                        className="h-[232px] w-full"
                        role="img"
                        aria-label="Units on hand by warehouse"
                    >
                        <BarChart
                            accessibilityLayer
                            data={onHandByWarehouse}
                            layout="vertical"
                            margin={{ left: 4, right: 16 }}
                        >
                            <CartesianGrid
                                horizontal={false}
                                strokeDasharray="3 3"
                            />
                            <XAxis type="number" hide allowDecimals={false} />
                            <YAxis
                                type="category"
                                dataKey="name"
                                tickLine={false}
                                axisLine={false}
                                width={96}
                                fontSize={11}
                            />
                            <ChartTooltip
                                cursor={false}
                                content={<ChartTooltipContent />}
                            />
                            <Bar
                                dataKey="quantity"
                                fill="var(--color-quantity)"
                                radius={4}
                                maxBarSize={44}
                            />
                        </BarChart>
                    </ChartContainer>
                </ChartCard>
            </div>

            {/* Lists */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Needs reordering
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {reorderList.length === 0 ? (
                            <p className="py-8 text-center text-muted-foreground text-sm">
                                All stock is above its reorder point.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Item</TableHead>
                                        <TableHead className="text-right">
                                            On hand
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Min
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Short by
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {reorderList.map((row) => (
                                        <TableRow key={`${row.type}:${row.id}`}>
                                            <TableCell>
                                                <Link
                                                    href={`${row.type === 'product' ? productsUrl : materialsUrl}?search=${encodeURIComponent(row.sku)}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {row.name}
                                                </Link>
                                                <div className="text-muted-foreground text-xs">
                                                    {row.sku} ·{' '}
                                                    {row.type === 'product'
                                                        ? 'Product'
                                                        : 'Raw material'}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatQuantity(row.on_hand)}
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {formatQuantity(row.min_stock)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Badge variant="destructive">
                                                    {formatQuantity(
                                                        row.deficit,
                                                    )}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Recent stock movements
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentMovements.length === 0 ? (
                            <p className="py-8 text-center text-muted-foreground text-sm">
                                No stock movements recorded yet.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Item</TableHead>
                                        <TableHead className="text-right">
                                            Qty
                                        </TableHead>
                                        <TableHead className="hidden sm:table-cell">
                                            When
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentMovements.map((m) => (
                                        <TableRow key={m.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {m.item}
                                                </div>
                                                <div className="text-muted-foreground text-xs">
                                                    {m.reason} · {m.warehouse}
                                                </div>
                                            </TableCell>
                                            <TableCell
                                                className={cn(
                                                    'text-right font-medium tabular-nums',
                                                    m.quantity >= 0
                                                        ? 'text-primary'
                                                        : 'text-destructive',
                                                )}
                                            >
                                                {m.quantity >= 0 ? '+' : '−'}
                                                {formatQuantity(
                                                    Math.abs(m.quantity),
                                                )}
                                            </TableCell>
                                            <TableCell className="hidden text-muted-foreground text-xs sm:table-cell">
                                                <span suppressHydrationWarning>
                                                    {timeAgo(m.created_at)}
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </TenantLayout>
    );
}
