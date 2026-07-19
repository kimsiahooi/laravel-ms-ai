import { Head } from '@inertiajs/react';
import { Factory, ShoppingCart, TrendingUp, TriangleAlert } from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    XAxis,
    YAxis,
} from 'recharts';
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
import { formatCompact, formatQuantity } from '@/lib/format';
import { dashboard } from '@/routes/tenant';
import locations from '@/routes/tenant/locations';
import products from '@/routes/tenant/products';
import purchaseOrders from '@/routes/tenant/purchase-orders';
import rawMaterials from '@/routes/tenant/raw-materials';
import stockMovements from '@/routes/tenant/stock-movements';
import warehouses from '@/routes/tenant/warehouses';
import type { TenantBrand, User } from '@/types';

type Kpis = {
    sales: { count: number; amount: number };
    purchases: { count: number; amount: number };
    production: { count: number; quantity: number };
    low_stock: number;
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

// One series → one hue; the bar's direction (right/left of zero) shows in vs out.
const movementsConfig = {
    net: { label: 'Net change', color: 'var(--chart-1)' },
} satisfies ChartConfig;

export default function TenantDashboard() {
    const {
        auth,
        tenant,
        organization,
        filters,
        kpis,
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

    // First-run setup chain: location → warehouse → catalog → BOM → stock → order.
    // Each step links to the page that completes it; the card hides once all done.
    const slug = tenant.slug;
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
                    icon={TrendingUp}
                    label="Sales"
                    value={formatQuantity(kpis.sales.amount)}
                    sub={`${kpis.sales.count} ${kpis.sales.count === 1 ? 'order' : 'orders'} fulfilled`}
                />
                <StatCard
                    icon={ShoppingCart}
                    label="Purchases"
                    value={formatQuantity(kpis.purchases.amount)}
                    sub={`${kpis.purchases.count} ${kpis.purchases.count === 1 ? 'order' : 'orders'} received`}
                />
                <StatCard
                    icon={Factory}
                    label="Production"
                    value={formatQuantity(kpis.production.quantity)}
                    sub={`${kpis.production.count} ${kpis.production.count === 1 ? 'build' : 'builds'} completed`}
                />
                <StatCard
                    icon={TriangleAlert}
                    label="Low / out of stock"
                    value={kpis.low_stock}
                    valueClassName={
                        kpis.low_stock > 0
                            ? 'text-amber-600 dark:text-amber-400'
                            : undefined
                    }
                    sub={
                        kpis.low_stock > 0
                            ? 'items need reordering now'
                            : "everything's above its reorder level"
                    }
                />
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <ChartCard
                    className="lg:col-span-2"
                    title="Sales vs Purchases"
                    description="Money in from sales and out on purchases, per day"
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
                    title="Stock movements"
                    description="Net change by reason over this period"
                    isEmpty={movements.length === 0}
                    emptyText="No stock movements in this range."
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
                            <Bar
                                dataKey="net"
                                fill="var(--color-net)"
                                radius={4}
                                maxBarSize={26}
                            />
                        </BarChart>
                    </ChartContainer>
                </ChartCard>
            </div>
        </TenantLayout>
    );
}
