import { Head } from '@inertiajs/react';
import {
    Boxes,
    Clock,
    Factory,
    PackageCheck,
    PackageX,
    TriangleAlert,
    Truck,
} from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    XAxis,
    YAxis,
} from 'recharts';
import {
    type AttentionItem,
    AttentionPanel,
} from '@/components/attention-panel';
import { ChartCard } from '@/components/chart-card';
import { DateRangePicker } from '@/components/date-range-picker';
import {
    OnboardingChecklist,
    type OnboardingStep,
} from '@/components/onboarding-checklist';
import { StatCard } from '@/components/stat-card';
import {
    type ChartConfig,
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { useDateRangeFilter } from '@/hooks/use-date-range-filter';
import { firstName, useTimeOfDayGreeting } from '@/hooks/use-greeting';
import { usePageProps } from '@/hooks/use-page-props';
import TenantLayout from '@/layouts/tenant-layout';
import { formatCompact, formatMoney, formatQuantity } from '@/lib/format';
import { dashboard } from '@/routes/tenant';
import locations from '@/routes/tenant/locations';
import productionOrders from '@/routes/tenant/production-orders';
import products from '@/routes/tenant/products';
import purchaseOrders from '@/routes/tenant/purchase-orders';
import rawMaterials from '@/routes/tenant/raw-materials';
import reports from '@/routes/tenant/reports';
import salesOrders from '@/routes/tenant/sales-orders';
import stockMovements from '@/routes/tenant/stock-movements';
import warehouses from '@/routes/tenant/warehouses';
import type { TenantBrand, User } from '@/types';

/** Period-scoped totals — these move when the date range changes. */
type Kpis = {
    currency: string;
    sales: { count: number; amount: number };
    purchases: { count: number; amount: number };
    production: { count: number; quantity: number };
};

/** How things stand right now, whatever range is selected. */
type Snapshot = {
    /** What the stock is worth, and how many items it couldn't put a price on. */
    stock_value: { amount: number; valued: number; unvalued: number };
    /** Purchase orders still on their way. */
    incoming: { count: number; amount: number; overdue: number };
    production: { active: number; blocked: number };
    low_stock: number;
    out_of_stock: number;
    ready_sales: number;
};

type TrendPoint = {
    day: string;
    label: string;
    sales: number;
    purchases: number;
};

type Movement = { reason: string; label: string; net: number };

type PageProps = {
    auth: { user: User | null };
    tenant: TenantBrand;
    organization: { name: string; slug: string; logo: string | null };
    filters: { from: string; to: string };
    kpis: Kpis;
    snapshot: Snapshot;
    series: TrendPoint[];
    movements: Movement[];
    onboarding: {
        location: boolean;
        warehouse: boolean;
        catalog: boolean;
        bom: boolean;
        stock: boolean;
        order: boolean;
    };
};

// Sales vs Purchases share one measure (amount) so they sit on a single axis;
// distinct categorical hues carry identity, and the legend names both series.
const trendConfig = {
    sales: { label: 'Sales', color: 'var(--chart-1)' },
    purchases: { label: 'Purchases', color: 'var(--chart-2)' },
} satisfies ChartConfig;

// A diverging pair: stock coming in vs going out, split at zero. Two hues carry
// the direction so it reads without having to check which side of zero a bar is.
const movementsConfig = {
    net: { label: 'Net change' },
    in: { label: 'Added', color: 'var(--chart-2)' },
    out: { label: 'Used', color: 'var(--chart-5)' },
} satisfies ChartConfig;

export default function TenantDashboard() {
    const {
        auth,
        tenant,
        organization,
        filters,
        kpis,
        snapshot,
        series,
        movements,
        onboarding,
    } = usePageProps<PageProps>();

    const greeting = useTimeOfDayGreeting();
    const greeterName = firstName(auth.user?.name, 'there');

    const base = dashboard.url({ tenant: tenant.slug });
    const applyRange = useDateRangeFilter(base, [
        'filters',
        'kpis',
        'series',
        'movements',
    ]);

    const hasTrend = series.some((d) => d.sales > 0 || d.purchases > 0);
    const slug = tenant.slug;

    // Be straight about what the stock value covers: it's priced from what was
    // paid, so anything never purchased has no price to go on.
    const { valued, unvalued } = snapshot.stock_value;
    const stockValueSub =
        unvalued > 0
            ? `${valued} ${valued === 1 ? 'item' : 'items'} priced · ${unvalued} not yet`
            : `${valued} ${valued === 1 ? 'item' : 'items'} in stock`;

    // Out of stock is the emergency; low-but-not-empty is a heads-up. Say whichever
    // is actually true rather than printing a "0 out of stock" alarm.
    const outOfStock = snapshot.out_of_stock;
    const runningLow = snapshot.low_stock - outOfStock;
    const stockAlertSub =
        outOfStock > 0
            ? `${outOfStock} out of stock · reorder now`
            : runningLow > 0
              ? `${runningLow} below the reorder level`
              : "everything's above its reorder level";

    // Only the things actually waiting on someone, each linking to where it's
    // dealt with. A zero count drops off the list rather than showing "0".
    const attention: AttentionItem[] = [];

    if (outOfStock > 0) {
        attention.push({
            key: 'out-of-stock',
            icon: PackageX,
            tone: 'critical',
            text: `${outOfStock} ${outOfStock === 1 ? 'item has' : 'items have'} run out`,
            // Reordering means raising a purchase order, so go where that happens.
            action: 'Reorder',
            href: purchaseOrders.index.url({ tenant: slug }),
        });
    }
    if (snapshot.production.blocked > 0) {
        attention.push({
            key: 'blocked-production',
            icon: Factory,
            tone: 'critical',
            text: `${snapshot.production.blocked} ${snapshot.production.blocked === 1 ? 'build cannot' : 'builds cannot'} start — not enough materials`,
            action: 'View',
            href: productionOrders.index.url({ tenant: slug }),
        });
    }
    if (snapshot.incoming.overdue > 0) {
        attention.push({
            key: 'overdue-purchases',
            icon: Clock,
            text: `${snapshot.incoming.overdue} purchase ${snapshot.incoming.overdue === 1 ? 'order is' : 'orders are'} past the delivery date`,
            action: 'Chase',
            href: purchaseOrders.index.url({ tenant: slug }),
        });
    }
    if (runningLow > 0) {
        attention.push({
            key: 'low-stock',
            icon: TriangleAlert,
            text: `${runningLow} ${runningLow === 1 ? 'item is' : 'items are'} running low`,
            action: 'Review',
            href: reports.index.url({ tenant: slug }),
        });
    }
    if (snapshot.ready_sales > 0) {
        attention.push({
            key: 'ready-sales',
            icon: PackageCheck,
            text: `${snapshot.ready_sales} sales ${snapshot.ready_sales === 1 ? 'order is' : 'orders are'} ready to ship`,
            action: 'Fulfil',
            href: salesOrders.index.url({ tenant: slug }),
        });
    }

    // Before the workspace is set up there is genuinely nothing to act on yet, so
    // "you're all caught up" would contradict the setup checklist above.
    const setupComplete = Object.values(onboarding).every(Boolean);

    // First-run setup chain: location → warehouse → catalog → BOM → stock → order.
    // Each step links to the page that completes it; the card hides once all done.
    const onboardingSteps: OnboardingStep[] = [
        {
            title: 'Add a location',
            description: 'Create the site or branch your warehouses sit in.',
            href: locations.index.url({ tenant: slug }),
            done: onboarding.location,
        },
        {
            title: 'Add a warehouse',
            description: 'Set up a place inside that location to hold stock.',
            href: warehouses.index.url({ tenant: slug }),
            done: onboarding.warehouse,
        },
        {
            title: 'Build your catalog',
            description:
                'Add the raw materials you buy and the products you make or sell.',
            href: rawMaterials.index.url({ tenant: slug }),
            done: onboarding.catalog,
        },
        {
            title: 'Set a bill of materials',
            description: 'List the raw materials each product is built from.',
            href: products.index.url({ tenant: slug }),
            done: onboarding.bom,
        },
        {
            title: 'Record stock in',
            description: 'Log your opening stock with a stock movement.',
            href: stockMovements.index.url({ tenant: slug }),
            done: onboarding.stock,
        },
        {
            title: 'Create your first order',
            description: 'Raise a purchase or sales order to start trading.',
            href: purchaseOrders.index.url({ tenant: slug }),
            done: onboarding.order,
        },
    ];

    return (
        <TenantLayout>
            <Head title={`Dashboard — ${tenant.name}`} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        Dashboard
                    </h1>
                    <p
                        className="text-muted-foreground text-sm"
                        suppressHydrationWarning
                    >
                        {greeting}, {greeterName}. Here's how{' '}
                        {organization.name} is doing this period.
                    </p>
                </div>
                <DateRangePicker
                    value={{ from: filters.from, to: filters.to }}
                    onChange={applyRange}
                />
            </div>

            <OnboardingChecklist steps={onboardingSteps} />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={Boxes}
                    label="Stock value"
                    value={formatMoney(
                        snapshot.stock_value.amount,
                        kpis.currency,
                    )}
                    sub={stockValueSub}
                    href={warehouses.index.url({ tenant: slug })}
                />
                <StatCard
                    icon={TriangleAlert}
                    label="Low / out of stock"
                    value={snapshot.low_stock}
                    // Red is reserved for stock that has actually run out.
                    tone={outOfStock > 0 ? 'critical' : 'default'}
                    sub={stockAlertSub}
                    href={reports.index.url({ tenant: slug })}
                />
                <StatCard
                    icon={Truck}
                    label="Incoming"
                    value={formatMoney(snapshot.incoming.amount, kpis.currency)}
                    sub={
                        snapshot.incoming.count === 0
                            ? 'no orders on their way'
                            : `${snapshot.incoming.count} ${snapshot.incoming.count === 1 ? 'order' : 'orders'} on the way${
                                  snapshot.incoming.overdue > 0
                                      ? ` · ${snapshot.incoming.overdue} late`
                                      : ''
                              }`
                    }
                    href={purchaseOrders.index.url({ tenant: slug })}
                />
                <StatCard
                    icon={Factory}
                    label="Production"
                    value={`${snapshot.production.active} active`}
                    sub={
                        snapshot.production.blocked > 0
                            ? `${snapshot.production.blocked} can't start · not enough materials`
                            : `${kpis.production.count} completed · ${formatQuantity(kpis.production.quantity)} ${kpis.production.quantity === 1 ? 'unit' : 'units'}`
                    }
                    href={productionOrders.index.url({ tenant: slug })}
                />
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <AttentionPanel
                    items={attention}
                    emptyText={
                        setupComplete
                            ? undefined
                            : 'Finish setting up your workspace and anything that needs doing will show up here.'
                    }
                />

                <ChartCard
                    className="lg:col-span-2"
                    title="Sales vs Purchases"
                    description={
                        hasTrend
                            ? `${formatMoney(kpis.sales.amount, kpis.currency)} in from sales, ${formatMoney(kpis.purchases.amount, kpis.currency)} out on purchases`
                            : 'Money in from sales and out on purchases, per day'
                    }
                    isEmpty={!hasTrend}
                    emptyText="No sales or purchases in this range yet."
                >
                    <ChartContainer
                        config={trendConfig}
                        className="h-64 w-full"
                        role="img"
                        aria-label="Sales and purchase totals per day over the selected range"
                    >
                        <AreaChart
                            accessibilityLayer
                            data={series}
                            margin={{ left: -8, right: 8, top: 4 }}
                        >
                            <defs>
                                <linearGradient
                                    id="fillSales"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="5%"
                                        stopColor="var(--color-sales)"
                                        stopOpacity={0.35}
                                    />
                                    <stop
                                        offset="95%"
                                        stopColor="var(--color-sales)"
                                        stopOpacity={0.04}
                                    />
                                </linearGradient>
                                <linearGradient
                                    id="fillPurchases"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="5%"
                                        stopColor="var(--color-purchases)"
                                        stopOpacity={0.35}
                                    />
                                    <stop
                                        offset="95%"
                                        stopColor="var(--color-purchases)"
                                        stopOpacity={0.04}
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
                                width={44}
                                fontSize={11}
                                tickFormatter={formatCompact}
                            />
                            <ChartTooltip
                                cursor={false}
                                content={
                                    <ChartTooltipContent indicator="line" />
                                }
                            />
                            <ChartLegend content={<ChartLegendContent />} />
                            <Area
                                dataKey="sales"
                                type="monotone"
                                stroke="var(--color-sales)"
                                strokeWidth={2}
                                fill="url(#fillSales)"
                                dot={false}
                            />
                            <Area
                                dataKey="purchases"
                                type="monotone"
                                stroke="var(--color-purchases)"
                                strokeWidth={2}
                                fill="url(#fillPurchases)"
                                dot={false}
                            />
                        </AreaChart>
                    </ChartContainer>
                </ChartCard>

                <ChartCard
                    className="lg:col-span-3"
                    title="Stock movements"
                    description="What added stock and what used it up over this period"
                    isEmpty={movements.length === 0}
                    emptyText="No stock movements in this range."
                    action={
                        <div className="flex items-center gap-3 text-muted-foreground text-xs">
                            <span className="flex items-center gap-1.5">
                                <span
                                    className="size-2 rounded-[2px]"
                                    style={{
                                        background: 'var(--chart-2)',
                                    }}
                                />
                                Added
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span
                                    className="size-2 rounded-[2px]"
                                    style={{
                                        background: 'var(--chart-5)',
                                    }}
                                />
                                Used
                            </span>
                        </div>
                    }
                >
                    <ChartContainer
                        config={movementsConfig}
                        className="h-64 w-full"
                        role="img"
                        aria-label="Net stock change grouped by movement reason"
                    >
                        <BarChart
                            accessibilityLayer
                            data={movements}
                            layout="vertical"
                            margin={{ left: 8, right: 16, top: 4, bottom: 4 }}
                        >
                            <CartesianGrid
                                horizontal={false}
                                strokeDasharray="3 3"
                            />
                            <XAxis
                                type="number"
                                tickLine={false}
                                axisLine={false}
                                fontSize={11}
                                tickFormatter={formatCompact}
                            />
                            <YAxis
                                type="category"
                                dataKey="label"
                                tickLine={false}
                                axisLine={false}
                                width={112}
                                fontSize={11}
                                interval={0}
                            />
                            <ChartTooltip
                                cursor={false}
                                content={<ChartTooltipContent />}
                            />
                            {/* A base fill keeps the tooltip swatch coloured; each
                                Cell then overrides it with the in/out hue. */}
                            <Bar
                                dataKey="net"
                                fill="var(--color-in)"
                                radius={4}
                                maxBarSize={26}
                            >
                                {movements.map((movement) => (
                                    <Cell
                                        key={movement.reason}
                                        fill={
                                            movement.net < 0
                                                ? 'var(--color-out)'
                                                : 'var(--color-in)'
                                        }
                                    />
                                ))}
                            </Bar>
                        </BarChart>
                    </ChartContainer>
                </ChartCard>
            </div>
        </TenantLayout>
    );
}
