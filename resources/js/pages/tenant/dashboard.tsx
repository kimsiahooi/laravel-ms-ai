import { Head, usePage } from '@inertiajs/react';
import { AtSign, Link2, Sparkles, UserRound } from 'lucide-react';
import { type ComponentType, type ReactNode, useEffect, useState } from 'react';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import TenantLayout from '@/layouts/tenant-layout';

type PageProps = {
    auth: { user: { name: string; email: string } | null };
    tenant: { slug: string; name: string } | null;
};

function InfoTile({
    icon: Icon,
    label,
    value,
}: {
    icon: ComponentType<{ className?: string }>;
    label: string;
    value: ReactNode;
}) {
    return (
        <Card className="p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <p className="truncate font-medium text-base">{value}</p>
                </div>
                <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-secondary text-foreground">
                    <Icon className="size-4" />
                </span>
            </div>
        </Card>
    );
}

export default function TenantDashboard() {
    const { auth, tenant } = usePage().props as unknown as PageProps;
    const [greeting, setGreeting] = useState('Welcome back');

    const firstName = auth.user?.name?.trim().split(/\s+/)[0] || 'there';

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
        <TenantLayout>
            <Head title={`Dashboard — ${tenant?.name ?? 'Workspace'}`} />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Dashboard
                </h1>
                <p
                    className="text-muted-foreground text-sm"
                    suppressHydrationWarning
                >
                    {greeting}, {firstName}. Welcome to{' '}
                    {tenant?.name ?? 'your workspace'}.
                </p>
            </div>

            <Card>
                <CardHeader className="gap-3">
                    <span className="grid size-11 place-items-center rounded-full bg-secondary text-foreground">
                        <Sparkles className="size-5" />
                    </span>
                    <CardTitle className="text-lg">
                        Your workspace is ready
                    </CardTitle>
                    <CardDescription className="max-w-prose">
                        You're signed in to {tenant?.name ?? 'your workspace'}.
                        This is your team's dedicated space — modules and tools
                        will appear here as they're added.
                    </CardDescription>
                </CardHeader>
            </Card>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <InfoTile
                    icon={UserRound}
                    label="Signed in as"
                    value={auth.user?.name ?? '—'}
                />
                <InfoTile
                    icon={AtSign}
                    label="Email"
                    value={auth.user?.email ?? '—'}
                />
                <InfoTile
                    icon={Link2}
                    label="Workspace URL"
                    value={`/${tenant?.slug ?? ''}`}
                />
            </div>
        </TenantLayout>
    );
}
