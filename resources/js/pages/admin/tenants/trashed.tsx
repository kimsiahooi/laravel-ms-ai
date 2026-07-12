import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ArchiveX,
    ArrowLeft,
    LoaderCircle,
    MoreHorizontal,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';
import { usePageProps } from '@/hooks/use-page-props';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import { timeAgo } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import { forceDestroy, index, restore, trashed } from '@/routes/admin/tenants';
import type { ResourceFilters } from '@/types';

type TrashedTenant = {
    name: string;
    slug: string;
    deleted_at: string;
};

type PageProps = {
    tenants: Paginator<TrashedTenant>;
    filters: ResourceFilters;
};

export default function AdminTenantsTrashed() {
    const { tenants, filters } = usePageProps<PageProps>();
    const getInitials = useInitials();

    const [restoring, setRestoring] = useState<TrashedTenant | null>(null);
    const [purging, setPurging] = useState<TrashedTenant | null>(null);
    const [confirmText, setConfirmText] = useState('');
    const [processing, setProcessing] = useState(false);

    const confirmRestore = () => {
        if (!restoring) {
            return;
        }

        router.patch(
            restore.url({ tenant: restoring.slug }),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: (_page) => {
                    setRestoring(null);
                },
                onError: () =>
                    toast.error(
                        'Could not restore the tenant. Please try again.',
                    ),
            },
        );
    };

    const closePurge = () => {
        setPurging(null);
        setConfirmText('');
    };

    const confirmPurge = () => {
        if (!purging || confirmText !== purging.slug) {
            return;
        }

        router.delete(forceDestroy.url({ tenant: purging.slug }), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: (_page) => {
                closePurge();
            },
            onError: () =>
                toast.error('Could not delete the tenant. Please try again.'),
        });
    };

    const columns: ColumnDef<TrashedTenant>[] = [
        {
            accessorKey: 'name',
            header: 'Tenant',
            cell: ({ row }) => (
                <div className="flex items-center gap-3">
                    <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-muted font-medium text-xs">
                        {getInitials(row.original.name)}
                    </span>
                    <div className="min-w-0">
                        <p className="truncate font-medium text-foreground">
                            {row.original.name}
                        </p>
                        <p className="truncate font-mono text-muted-foreground text-xs sm:hidden">
                            /{row.original.slug}
                        </p>
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'slug',
            header: 'Address',
            meta: { className: 'hidden sm:table-cell' },
            cell: ({ row }) => (
                <Badge variant="outline" className="font-mono font-normal">
                    /{row.original.slug}
                </Badge>
            ),
        },
        {
            accessorKey: 'deleted_at',
            header: 'Deleted',
            meta: { className: 'hidden md:table-cell' },
            cell: ({ row }) => (
                <span
                    className="whitespace-nowrap text-muted-foreground tabular-nums"
                    suppressHydrationWarning
                >
                    {timeAgo(row.original.deleted_at)}
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) => {
                const tenant = row.original;

                return (
                    <div className="flex items-center justify-end gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            className="hidden sm:inline-flex"
                            onClick={() => setRestoring(tenant)}
                        >
                            <RotateCcw className="size-3.5" />
                            Restore
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    aria-label={`Actions for ${tenant.name}`}
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-52">
                                <DropdownMenuItem
                                    className="sm:hidden"
                                    onSelect={() => setRestoring(tenant)}
                                >
                                    <RotateCcw className="size-4" />
                                    Restore
                                </DropdownMenuItem>
                                <DropdownMenuSeparator className="sm:hidden" />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setPurging(tenant)}
                                >
                                    <Trash2 className="size-4" />
                                    Delete permanently
                                </DropdownMenuItem>
                                <DropdownMenuSeparator className="md:hidden" />
                                <div
                                    className="px-2 py-1.5 text-muted-foreground text-xs md:hidden"
                                    suppressHydrationWarning
                                >
                                    Deleted {timeAgo(tenant.deleted_at)}
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
    ];

    return (
        <CentralAdminLayout
            breadcrumbs={[
                { title: 'Dashboard', href: dashboard.url() },
                { title: 'Tenants', href: index.url() },
                { title: 'Archived', href: trashed.url() },
            ]}
        >
            <Head title="Archived tenants" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        Archived tenants
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        These workspaces have been deleted but can still be
                        restored. Restore one, or permanently delete it to erase
                        all of its data for good.
                    </p>
                </div>
                <Button asChild variant="outline">
                    <Link href={index.url()}>
                        <ArrowLeft className="size-4" />
                        Back to tenants
                    </Link>
                </Button>
            </div>

            <DataTable
                columns={columns}
                paginator={tenants}
                filters={filters}
                baseUrl={trashed.url()}
                only={['tenants', 'filters']}
                getRowId={(tenant) => tenant.slug}
                title="Archived"
                searchPlaceholder="Search by name or address…"
                emptyState={
                    <EmptyState
                        icon={ArchiveX}
                        title="Archive is empty"
                        description="Deleted tenants show up here. You can restore them or permanently remove them."
                    />
                }
            />

            {/* Restore confirmation */}
            <Dialog
                open={restoring !== null}
                onOpenChange={(next) => {
                    if (processing) {
                        return;
                    }

                    if (!next) {
                        setRestoring(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Restore tenant</DialogTitle>
                        <DialogDescription>
                            Bring “{restoring?.name}” back online? Its workspace
                            (/{restoring?.slug}) becomes accessible again.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={processing}
                            onClick={() => setRestoring(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={processing}
                            onClick={confirmRestore}
                        >
                            {processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <RotateCcw className="size-4" />
                            )}
                            Restore
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Permanent delete — type-to-confirm */}
            <Dialog
                open={purging !== null}
                onOpenChange={(next) => {
                    if (processing) {
                        return;
                    }

                    if (!next) {
                        closePurge();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete tenant permanently</DialogTitle>
                        <DialogDescription>
                            This permanently deletes “{purging?.name}” and
                            erases all of its data. This cannot be undone. Type{' '}
                            <span className="font-medium font-mono text-foreground">
                                {purging?.slug}
                            </span>{' '}
                            to confirm.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="confirm-slug" className="sr-only">
                            Type the workspace address to confirm
                        </Label>
                        <Input
                            id="confirm-slug"
                            value={confirmText}
                            onChange={(event) =>
                                setConfirmText(event.target.value)
                            }
                            autoComplete="off"
                            placeholder={purging?.slug}
                            className="font-mono"
                            disabled={processing}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={processing}
                            onClick={closePurge}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={
                                confirmText !== purging?.slug || processing
                            }
                            onClick={confirmPurge}
                        >
                            {processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Trash2 className="size-4" />
                            )}
                            Delete permanently
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </CentralAdminLayout>
    );
}
