import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    CalendarPlus,
    CalendarRange,
    Clock,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { ChartCard } from '@/components/chart-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import {
    type ChartConfig,
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { usePageProps } from '@/hooks/use-page-props';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import { timeAgo } from '@/lib/format';
import { index as tenantsIndex } from '@/routes/admin/tenants';

type Stats = {
    total: number;
    added_today: number;
    last_7_days: number;
    newest: { name: string; created_at: string } | null;
};

type SignupPoint = { date: string; label: string; count: number };

type PageProps = {
    auth: { user: { name: string; email: string } | null };
    stats: Stats;
    signups: SignupPoint[];
};

const signupsConfig = {
    count: { label: 'Signups', color: 'var(--chart-1)' },
} satisfies ChartConfig;

export default function AdminDashboard() {
    const { auth, stats, signups } = usePageProps<PageProps>();
    const [greeting, setGreeting] = useState('Welcome back');
    const hasSignups = signups.some((d) => d.count > 0);

    const firstName = auth.user?.name?.trim().split(/\s+/)[0] || 'Admin';

    // Time-of-day greeting computed after mount to avoid SSR/timezone mismatch.
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

    return (
        <CentralAdminLayout>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        Dashboard
                    </h1>
                    <p
                        className="text-muted-foreground text-sm"
                        suppressHydrationWarning
                    >
                        {greeting}, {firstName}. Here's an overview of every
                        tenant workspace.
                    </p>
                </div>
                <Button asChild>
                    <Link href={tenantsIndex.url()}>
                        Manage tenants
                        <ArrowRight className="size-4" />
                    </Link>
                </Button>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={Building2}
                    label="Total tenants"
                    value={stats.total}
                    sub="workspaces created"
                />
                <StatCard
                    icon={CalendarPlus}
                    label="Added today"
                    value={stats.added_today}
                    sub="Added today (UTC)"
                />
                <StatCard
                    icon={CalendarRange}
                    label="Last 7 days"
                    value={stats.last_7_days}
                    sub="newly added"
                />
                <StatCard
                    icon={Clock}
                    label="Newest tenant"
                    value={stats.newest?.name ?? '—'}
                    valueClassName="truncate text-base font-medium"
                    sub={
                        stats.newest ? (
                            <span suppressHydrationWarning>
                                {timeAgo(stats.newest.created_at)}
                            </span>
                        ) : (
                            'No tenants yet'
                        )
                    }
                />
            </div>

            <ChartCard
                title="Tenant signups"
                description="New workspaces added each day, last 30 days"
                isEmpty={!hasSignups}
                emptyText="No signups in the last 30 days."
            >
                <ChartContainer
                    config={signupsConfig}
                    className="h-58 w-full"
                    role="img"
                    aria-label="New tenant signups per day over the last 30 days"
                >
                    <BarChart
                        accessibilityLayer
                        data={signups}
                        margin={{ left: -12, right: 8, top: 4 }}
                    >
                        <CartesianGrid vertical={false} strokeDasharray="3 3" />
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
                            dataKey="count"
                            fill="var(--color-count)"
                            radius={[4, 4, 0, 0]}
                            maxBarSize={44}
                        />
                    </BarChart>
                </ChartContainer>
            </ChartCard>
        </CentralAdminLayout>
    );
}
