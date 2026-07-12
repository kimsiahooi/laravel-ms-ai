import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowRight, History } from 'lucide-react';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { usePageProps } from '@/hooks/use-page-props';
import TenantLayout from '@/layouts/tenant-layout';
import { absoluteDate, timeAgo } from '@/lib/format';
import { dashboard } from '@/routes/tenant';
import activityRoutes from '@/routes/tenant/activity';
import type { TenantPageProps } from '@/types';

type Activity = App.Data.ActivityData;

type PageProps = TenantPageProps & {
    activities: Paginator<Activity>;
};

const EVENT_LABEL: Record<string, string> = {
    created: 'Created',
    updated: 'Updated',
    deleted: 'Deleted',
};

function eventBadge(event: string | null) {
    const label = (event && EVENT_LABEL[event]) ?? event ?? '—';
    if (event === 'deleted') {
        return <Badge variant="destructive">{label}</Badge>;
    }
    if (event === 'created') {
        return (
            <Badge className="border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/40 dark:bg-emerald-400/10 dark:text-emerald-400">
                {label}
            </Badge>
        );
    }
    return <Badge variant="secondary">{label}</Badge>;
}

export default function ActivityIndex() {
    const { activities, filters, tenant } = usePageProps<PageProps>();
    const base = activityRoutes.index.url({ tenant: tenant.slug });

    const columns: ColumnDef<Activity>[] = [
        {
            accessorKey: 'created_at',
            header: 'When',
            cell: ({ row }) => (
                <span
                    className="whitespace-nowrap text-muted-foreground tabular-nums"
                    title={absoluteDate(row.original.created_at)}
                    suppressHydrationWarning
                >
                    {timeAgo(row.original.created_at)}
                </span>
            ),
        },
        {
            accessorKey: 'causer',
            header: 'Who',
            cell: ({ row }) => row.original.causer ?? 'System',
            meta: { className: 'text-muted-foreground' },
        },
        {
            accessorKey: 'event',
            header: 'Action',
            cell: ({ row }) => eventBadge(row.original.event),
        },
        {
            id: 'record',
            header: 'Record',
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="font-medium text-foreground">
                        {row.original.subject}
                    </span>
                    <span className="text-muted-foreground text-xs">
                        {row.original.subject_type}
                    </span>
                </div>
            ),
        },
        {
            id: 'changes',
            header: 'Changes',
            cell: ({ row }) => {
                const changes = row.original.changes;
                if (changes.length === 0) {
                    return <span className="text-muted-foreground">—</span>;
                }
                return (
                    <div className="space-y-0.5 text-xs">
                        {changes.map((change) => (
                            <div
                                key={change.field}
                                className="flex flex-wrap items-center gap-1"
                            >
                                <span className="font-medium">
                                    {change.field}:
                                </span>
                                {change.old !== null && (
                                    <>
                                        <span className="text-muted-foreground line-through">
                                            {change.old}
                                        </span>
                                        <ArrowRight className="size-3 shrink-0 text-muted-foreground" />
                                    </>
                                )}
                                <span className="text-foreground">
                                    {change.new ?? '—'}
                                </span>
                            </div>
                        ))}
                    </div>
                );
            },
            meta: { className: 'hidden md:table-cell' },
        },
    ];

    return (
        <TenantLayout
            breadcrumbs={[
                {
                    title: 'Dashboard',
                    href: dashboard.url({ tenant: tenant.slug }),
                },
                { title: 'Activity', href: base },
            ]}
        >
            <Head title="Activity" />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Activity
                </h1>
                <p className="text-muted-foreground text-sm">
                    A history of who added, changed, or removed records — and
                    exactly what changed.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={activities}
                filters={filters}
                baseUrl={base}
                only={['activities', 'filters']}
                getRowId={(item) => String(item.id)}
                title="Activity"
                searchPlaceholder="Search by action, record or person…"
                emptyState={
                    <EmptyState
                        icon={History}
                        title="No activity yet"
                        description="As you add and edit records, a full history of every change will appear here."
                    />
                }
            />
        </TenantLayout>
    );
}
