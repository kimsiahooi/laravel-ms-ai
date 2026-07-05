import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    CalendarPlus,
    CalendarRange,
    Clock,
} from 'lucide-react';
import { type ComponentType, type ReactNode, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import { timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';

type Stats = {
    total: number;
    added_today: number;
    last_7_days: number;
    newest: { name: string; created_at: string } | null;
};

type PageProps = {
    auth: { user: { name: string; email: string } | null };
    stats: Stats;
};

function StatCard({
    icon: Icon,
    label,
    value,
    sub,
    valueClassName,
}: {
    icon: ComponentType<{ className?: string }>;
    label: string;
    value: ReactNode;
    sub: ReactNode;
    valueClassName?: string;
}) {
    return (
        <Card className="p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <p
                        className={cn(
                            'font-semibold text-2xl tabular-nums',
                            valueClassName,
                        )}
                    >
                        {value}
                    </p>
                    <p className="truncate text-muted-foreground text-xs">
                        {sub}
                    </p>
                </div>
                <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-secondary text-foreground">
                    <Icon className="size-4" />
                </span>
            </div>
        </Card>
    );
}

export default function AdminDashboard() {
    const { auth, stats } = usePage().props as unknown as PageProps;
    const [greeting, setGreeting] = useState('Welcome back');

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
            <Head title="Admin" />

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
                    <Link href="/admin/tenants">
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
                    sub="workspaces provisioned"
                />
                <StatCard
                    icon={CalendarPlus}
                    label="Added today"
                    value={stats.added_today}
                    sub="provisioned today (UTC)"
                />
                <StatCard
                    icon={CalendarRange}
                    label="Last 7 days"
                    value={stats.last_7_days}
                    sub="newly provisioned"
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
        </CentralAdminLayout>
    );
}
