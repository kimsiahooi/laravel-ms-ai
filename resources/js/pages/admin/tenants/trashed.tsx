import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArchiveX,
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    LoaderCircle,
    MoreHorizontal,
    RotateCcw,
    Search,
    SearchX,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import { timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';

type TrashedTenant = {
    name: string;
    slug: string;
    deleted_at: string;
};

type Paginator<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type PageProps = {
    tenants: Paginator<TrashedTenant>;
    filters: { search: string; per_page: number };
    flash: { success: string | null };
};

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

const listReload = (onStart: () => void, onFinish: () => void) => ({
    only: ['tenants', 'filters'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
    onStart,
    onFinish,
});

function flashToast(page: { props: unknown }): void {
    const message = (page.props as PageProps).flash?.success;
    if (message) {
        toast.success(message);
    }
}

export default function AdminTenantsTrashed() {
    const { tenants, filters } = usePage().props as unknown as PageProps;
    const getInitials = useInitials();

    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [restoring, setRestoring] = useState<TrashedTenant | null>(null);
    const [purging, setPurging] = useState<TrashedTenant | null>(null);
    const [confirmText, setConfirmText] = useState('');
    const [processing, setProcessing] = useState(false);
    const searchRef = useRef<HTMLInputElement>(null);

    // Debounced server-side search (trimmed guard mirrors the index page).
    useEffect(() => {
        const q = search.trim();

        if (q === filters.search) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.get(
                '/admin/tenants/trashed',
                { search: q || undefined, per_page: filters.per_page },
                listReload(
                    () => setLoading(true),
                    () => setLoading(false),
                ),
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search, filters.search, filters.per_page]);

    const visit = (
        url: string | null,
        data: Record<string, string | number | undefined> = {},
    ) => {
        if (url === null) {
            return;
        }

        router.get(
            url,
            data,
            listReload(
                () => setLoading(true),
                () => setLoading(false),
            ),
        );
    };

    const clearSearch = () => {
        setSearch('');
        searchRef.current?.focus();
    };

    const confirmRestore = () => {
        if (!restoring) {
            return;
        }

        router.patch(
            `/admin/tenants/${restoring.slug}/restore`,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: (page) => {
                    setRestoring(null);
                    flashToast(page);
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

        router.delete(`/admin/tenants/${purging.slug}/force`, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: (page) => {
                closePurge();
                flashToast(page);
            },
            onError: () =>
                toast.error('Could not delete the tenant. Please try again.'),
        });
    };

    return (
        <CentralAdminLayout>
            <Head title="Archived tenants" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        Archived tenants
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Soft-deleted workspaces. Restore one, or permanently
                        delete it to drop its database for good.
                    </p>
                </div>
                <Button asChild variant="outline">
                    <Link href="/admin/tenants">
                        <ArrowLeft className="size-4" />
                        Back to tenants
                    </Link>
                </Button>
            </div>

            {tenants.total === 0 && filters.search === '' ? (
                <Card>
                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                        <span className="grid size-12 place-items-center rounded-xl bg-secondary text-foreground">
                            <ArchiveX className="size-6" />
                        </span>
                        <div className="space-y-1">
                            <h3 className="font-semibold text-lg">
                                Archive is empty
                            </h3>
                            <p className="mx-auto max-w-sm text-muted-foreground text-sm">
                                Deleted tenants show up here. You can restore
                                them or permanently remove them.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardHeader className="gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <CardTitle>Archived</CardTitle>
                                <Badge
                                    variant="secondary"
                                    className="tabular-nums"
                                >
                                    {tenants.total}
                                </Badge>
                            </div>
                            <div className="relative w-full sm:w-64">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    ref={searchRef}
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search name or slug…"
                                    aria-label="Search archived tenants"
                                    className="px-9"
                                />
                                {loading ? (
                                    <LoaderCircle className="absolute top-1/2 right-2.5 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                                ) : (
                                    search !== '' && (
                                        <button
                                            type="button"
                                            onClick={clearSearch}
                                            aria-label="Clear search"
                                            className="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    )
                                )}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <p role="status" aria-live="polite" className="sr-only">
                            {tenants.data.length > 0
                                ? `Showing ${tenants.from} to ${tenants.to} of ${tenants.total} archived tenants`
                                : `No archived tenants match "${filters.search}"`}
                        </p>
                        <div
                            aria-busy={loading}
                            className={cn(
                                'overflow-x-auto transition-opacity',
                                loading && 'pointer-events-none opacity-60',
                            )}
                        >
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-border border-b">
                                        <th className="h-10 px-4 text-left font-medium text-muted-foreground text-xs uppercase tracking-wide">
                                            Tenant
                                        </th>
                                        <th className="hidden h-10 px-4 text-left font-medium text-muted-foreground text-xs uppercase tracking-wide sm:table-cell">
                                            Slug
                                        </th>
                                        <th className="hidden h-10 px-4 text-left font-medium text-muted-foreground text-xs uppercase tracking-wide md:table-cell">
                                            Deleted
                                        </th>
                                        <th className="h-10 px-4 text-right">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tenants.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={4}>
                                                <div className="flex flex-col items-center gap-2 py-12 text-center">
                                                    <SearchX className="size-6 text-muted-foreground" />
                                                    <p className="text-muted-foreground text-sm">
                                                        No archived tenants
                                                        match “{filters.search}”
                                                    </p>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={clearSearch}
                                                    >
                                                        Clear search
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        tenants.data.map((tenant) => (
                                            <tr
                                                key={tenant.slug}
                                                className="border-border border-b transition-colors last:border-0 hover:bg-muted/50"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-muted font-medium text-xs">
                                                            {getInitials(
                                                                tenant.name,
                                                            )}
                                                        </span>
                                                        <div className="min-w-0">
                                                            <p className="truncate font-medium text-foreground">
                                                                {tenant.name}
                                                            </p>
                                                            <p className="truncate font-mono text-muted-foreground text-xs sm:hidden">
                                                                /{tenant.slug}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="hidden px-4 py-3 sm:table-cell">
                                                    <Badge
                                                        variant="outline"
                                                        className="font-mono font-normal"
                                                    >
                                                        /{tenant.slug}
                                                    </Badge>
                                                </td>
                                                <td className="hidden px-4 py-3 md:table-cell">
                                                    <span
                                                        className="whitespace-nowrap text-muted-foreground tabular-nums"
                                                        suppressHydrationWarning
                                                    >
                                                        {timeAgo(
                                                            tenant.deleted_at,
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="hidden sm:inline-flex"
                                                            onClick={() =>
                                                                setRestoring(
                                                                    tenant,
                                                                )
                                                            }
                                                        >
                                                            <RotateCcw className="size-3.5" />
                                                            Restore
                                                        </Button>
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-8"
                                                                    aria-label={`Actions for ${tenant.name}`}
                                                                >
                                                                    <MoreHorizontal className="size-4" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent
                                                                align="end"
                                                                className="w-52"
                                                            >
                                                                <DropdownMenuItem
                                                                    className="sm:hidden"
                                                                    onSelect={() =>
                                                                        setRestoring(
                                                                            tenant,
                                                                        )
                                                                    }
                                                                >
                                                                    <RotateCcw className="size-4" />
                                                                    Restore
                                                                </DropdownMenuItem>
                                                                <DropdownMenuSeparator className="sm:hidden" />
                                                                <DropdownMenuItem
                                                                    variant="destructive"
                                                                    onSelect={() =>
                                                                        setPurging(
                                                                            tenant,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="size-4" />
                                                                    Delete
                                                                    permanently
                                                                </DropdownMenuItem>
                                                                <DropdownMenuSeparator className="md:hidden" />
                                                                <div
                                                                    className="px-2 py-1.5 text-muted-foreground text-xs md:hidden"
                                                                    suppressHydrationWarning
                                                                >
                                                                    Deleted{' '}
                                                                    {timeAgo(
                                                                        tenant.deleted_at,
                                                                    )}
                                                                </div>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {tenants.data.length > 0 && (
                            <div className="flex flex-col items-center justify-between gap-4 border-border border-t px-4 py-3 sm:flex-row">
                                <p className="text-muted-foreground text-sm">
                                    Showing{' '}
                                    <span className="font-medium text-foreground tabular-nums">
                                        {tenants.from}
                                    </span>
                                    –
                                    <span className="font-medium text-foreground tabular-nums">
                                        {tenants.to}
                                    </span>{' '}
                                    of{' '}
                                    <span className="font-medium text-foreground tabular-nums">
                                        {tenants.total}
                                    </span>{' '}
                                    archived
                                </p>
                                <div className="flex items-center gap-3">
                                    <div className="flex items-center gap-2">
                                        <span className="hidden text-muted-foreground text-sm sm:inline">
                                            Per page
                                        </span>
                                        <Select
                                            value={String(tenants.per_page)}
                                            disabled={loading}
                                            onValueChange={(value) =>
                                                visit(
                                                    '/admin/tenants/trashed',
                                                    {
                                                        search:
                                                            filters.search ||
                                                            undefined,
                                                        per_page: Number(value),
                                                    },
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                className="h-8 w-[4.25rem]"
                                                aria-label="Rows per page"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {PER_PAGE_OPTIONS.map((n) => (
                                                    <SelectItem
                                                        key={n}
                                                        value={String(n)}
                                                    >
                                                        {n}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-8"
                                            disabled={
                                                !tenants.prev_page_url ||
                                                loading
                                            }
                                            onClick={() =>
                                                visit(tenants.prev_page_url)
                                            }
                                            aria-label="Previous page"
                                        >
                                            <ChevronLeft className="size-4" />
                                        </Button>
                                        <span className="px-1 text-muted-foreground text-sm tabular-nums">
                                            Page {tenants.current_page} of{' '}
                                            {tenants.last_page}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-8"
                                            disabled={
                                                !tenants.next_page_url ||
                                                loading
                                            }
                                            onClick={() =>
                                                visit(tenants.next_page_url)
                                            }
                                            aria-label="Next page"
                                        >
                                            <ChevronRight className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

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
                            This permanently deletes “{purging?.name}” and drops
                            its database. This cannot be undone. Type{' '}
                            <span className="font-mono font-medium text-foreground">
                                {purging?.slug}
                            </span>{' '}
                            to confirm.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="confirm-slug" className="sr-only">
                            Type the tenant slug to confirm
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
