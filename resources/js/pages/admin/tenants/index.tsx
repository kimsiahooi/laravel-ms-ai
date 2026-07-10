import { Form, Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Archive,
    Building2,
    Check,
    Copy,
    ExternalLink,
    Eye,
    EyeOff,
    LoaderCircle,
    MoreHorizontal,
    Plus,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { DataTable, type Paginator } from '@/components/data-table';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
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
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';
import { useInitials } from '@/hooks/use-initials';
import { usePageProps } from '@/hooks/use-page-props';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import { absoluteDate, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { FlashSuccess, ResourceFilters } from '@/types';

type Tenant = {
    name: string;
    slug: string;
    created_at: string;
};

type PageProps = {
    tenants: Paginator<Tenant>;
    filters: ResourceFilters;
    flash: FlashSuccess;
};

const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFKD')
        .replace(/\p{M}/gu, '') // strip combining marks left by NFKD
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function flashToast(page: { props: unknown }): void {
    const message = (page.props as PageProps).flash?.success;
    if (message) {
        toast.success(message);
    }
}

export default function AdminTenantsIndex() {
    const { tenants, filters } = usePageProps<PageProps>();
    const getInitials = useInitials();
    const [, copy] = useClipboard();

    const [open, setOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [name, setName] = useState('');
    const [slug, setSlug] = useState('');
    const [slugTouched, setSlugTouched] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [copiedSlug, setCopiedSlug] = useState<string | null>(null);
    const [deleting, setDeleting] = useState<Tenant | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const copyTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const origin = typeof window === 'undefined' ? '' : window.location.origin;

    useEffect(
        () => () => {
            if (copyTimer.current) {
                clearTimeout(copyTimer.current);
            }
        },
        [],
    );

    const slugInvalid = slug !== '' && !SLUG_PATTERN.test(slug);

    const handleCopy = async (
        tenantSlug: string,
        text: string,
        label: string,
    ) => {
        const ok = await copy(text);

        if (!ok) {
            toast.error("Couldn't copy — clipboard unavailable");

            return;
        }

        toast.success(label);
        setCopiedSlug(tenantSlug);

        if (copyTimer.current) {
            clearTimeout(copyTimer.current);
        }

        copyTimer.current = setTimeout(() => setCopiedSlug(null), 1500);
    };

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }

        router.delete(`/admin/tenants/${deleting.slug}`, {
            preserveScroll: true,
            onStart: () => setDeleteProcessing(true),
            onFinish: () => setDeleteProcessing(false),
            onSuccess: (page) => {
                setDeleting(null);
                flashToast(page);
            },
            onError: () =>
                toast.error('Could not delete the tenant. Please try again.'),
        });
    };

    const resetForm = () => {
        setName('');
        setSlug('');
        setSlugTouched(false);
        setShowPassword(false);
    };

    const columns: ColumnDef<Tenant>[] = [
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
            header: 'Slug',
            meta: { className: 'hidden sm:table-cell' },
            cell: ({ row }) => {
                const copied = copiedSlug === row.original.slug;

                return (
                    <div className="flex items-center gap-1.5">
                        <button
                            type="button"
                            onClick={() =>
                                handleCopy(
                                    row.original.slug,
                                    row.original.slug,
                                    'Slug copied',
                                )
                            }
                            title="Copy slug"
                            className="cursor-pointer rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <Badge
                                variant="outline"
                                className="font-mono font-normal"
                            >
                                /{row.original.slug}
                            </Badge>
                        </button>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7"
                                    onClick={() =>
                                        handleCopy(
                                            row.original.slug,
                                            row.original.slug,
                                            'Slug copied',
                                        )
                                    }
                                    aria-label="Copy slug"
                                >
                                    {copied ? (
                                        <Check className="size-3.5" />
                                    ) : (
                                        <Copy className="size-3.5" />
                                    )}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {copied ? 'Copied' : 'Copy slug'}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                );
            },
        },
        {
            accessorKey: 'created_at',
            header: 'Created',
            meta: { className: 'hidden md:table-cell' },
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
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) => {
                const tenant = row.original;
                const loginPath = `/${tenant.slug}/login`;

                return (
                    <div className="flex items-center justify-end gap-1">
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="hidden sm:inline-flex"
                        >
                            <a
                                href={loginPath}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLink className="size-3.5" />
                                Open
                            </a>
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
                                <DropdownMenuItem asChild className="sm:hidden">
                                    <a
                                        href={loginPath}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <ExternalLink className="size-4" />
                                        Open workspace
                                    </a>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() =>
                                        handleCopy(
                                            tenant.slug,
                                            `${origin}${loginPath}`,
                                            'Workspace URL copied',
                                        )
                                    }
                                >
                                    <Copy className="size-4" />
                                    Copy workspace URL
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() =>
                                        handleCopy(
                                            tenant.slug,
                                            tenant.slug,
                                            'Slug copied',
                                        )
                                    }
                                >
                                    <Copy className="size-4" />
                                    Copy slug
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setDeleting(tenant)}
                                >
                                    <Trash2 className="size-4" />
                                    Delete tenant
                                </DropdownMenuItem>
                                <div
                                    className="px-2 py-1.5 text-muted-foreground text-xs md:hidden"
                                    suppressHydrationWarning
                                >
                                    Created {timeAgo(tenant.created_at)}
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
                { title: 'Dashboard', href: '/admin/dashboard' },
                { title: 'Tenants', href: '/admin/tenants' },
            ]}
        >
            <Head title="Tenants" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        Tenants
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Provision, search, and manage every tenant workspace.
                    </p>
                </div>
                <Button asChild variant="outline">
                    <Link href="/admin/tenants/trashed">
                        <Archive className="size-4" />
                        Archived
                    </Link>
                </Button>
            </div>

            <DataTable
                columns={columns}
                paginator={tenants}
                filters={filters}
                baseUrl="/admin/tenants"
                only={['tenants', 'filters']}
                getRowId={(tenant) => tenant.slug}
                title="Tenants"
                searchPlaceholder="Search name or slug…"
                toolbar={
                    <Button onClick={() => setOpen(true)} className="shrink-0">
                        <Plus className="size-4" />
                        New tenant
                    </Button>
                }
                emptyState={
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <span className="grid size-12 place-items-center rounded-xl bg-secondary text-foreground">
                                <Building2 className="size-6" />
                            </span>
                            <div className="space-y-1">
                                <h3 className="font-semibold text-lg">
                                    No tenants yet
                                </h3>
                                <p className="mx-auto max-w-sm text-muted-foreground text-sm">
                                    Provision your first workspace to get
                                    started. Each tenant gets an isolated
                                    database and its own login URL.
                                </p>
                            </div>
                            <Button onClick={() => setOpen(true)}>
                                <Plus className="size-4" />
                                Create your first tenant
                            </Button>
                        </CardContent>
                    </Card>
                }
            />

            <Dialog
                open={open}
                onOpenChange={(next) => {
                    if (submitting) {
                        return;
                    }

                    setOpen(next);

                    // Reset the draft on any user-driven dismiss so reopening is
                    // consistent — controlled name/slug otherwise persist while the
                    // uncontrolled admin_* fields remount empty.
                    if (!next) {
                        resetForm();
                    }
                }}
            >
                <DialogContent
                    className="gap-0 p-0 sm:max-w-md"
                    onInteractOutside={(event) => {
                        if (submitting) {
                            event.preventDefault();
                        }
                    }}
                    onEscapeKeyDown={(event) => {
                        if (submitting) {
                            event.preventDefault();
                        }
                    }}
                >
                    <DialogHeader className="gap-3 border-border border-b p-4 text-left">
                        <span className="grid size-9 place-items-center rounded-lg bg-secondary text-foreground">
                            <Building2 className="size-5" />
                        </span>
                        <div className="space-y-1">
                            <DialogTitle>Create a tenant</DialogTitle>
                            <DialogDescription>
                                Provision an isolated workspace and seed its
                                first admin user.
                            </DialogDescription>
                        </div>
                    </DialogHeader>

                    <Form
                        action="/admin/tenants"
                        method="post"
                        resetOnSuccess
                        disableWhileProcessing
                        onStart={() => setSubmitting(true)}
                        onFinish={() => setSubmitting(false)}
                        onSuccess={(page) => {
                            setOpen(false);
                            resetForm();

                            // Toast here (fires once per submit) rather than off a
                            // flash.success effect, which drops repeat identical
                            // messages and re-fires stale on back/forward nav.
                            const message = (page.props as unknown as PageProps)
                                .flash?.success;

                            if (message) {
                                toast.success(message);
                            }
                        }}
                        className="flex min-h-0 flex-col"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="max-h-[60vh] space-y-6 overflow-y-auto px-4 py-5">
                                    <div className="space-y-4">
                                        <p className="font-medium text-muted-foreground text-xs uppercase tracking-wide">
                                            Organization
                                        </p>
                                        <div className="space-y-2">
                                            <Label htmlFor="name">
                                                Organization name
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                value={name}
                                                onChange={(event) => {
                                                    setName(event.target.value);
                                                    if (!slugTouched) {
                                                        setSlug(
                                                            slugify(
                                                                event.target
                                                                    .value,
                                                            ),
                                                        );
                                                    }
                                                }}
                                                required
                                                autoFocus
                                                autoComplete="off"
                                                placeholder="Acme Manufacturing"
                                                aria-invalid={!!errors.name}
                                                aria-describedby={
                                                    errors.name
                                                        ? 'name-error'
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                id="name-error"
                                                role="alert"
                                                message={errors.name}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="slug">
                                                Workspace URL
                                            </Label>
                                            <div className="flex items-stretch overflow-hidden rounded-md border border-input focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                                                <span className="flex items-center whitespace-nowrap border-input border-r bg-muted px-2 text-muted-foreground text-xs">
                                                    {origin}/
                                                </span>
                                                <input
                                                    id="slug"
                                                    name="slug"
                                                    value={slug}
                                                    onChange={(event) => {
                                                        setSlugTouched(true);
                                                        setSlug(
                                                            event.target.value.toLowerCase(),
                                                        );
                                                    }}
                                                    onBlur={() =>
                                                        setSlug((current) =>
                                                            current.replace(
                                                                /-+$/,
                                                                '',
                                                            ),
                                                        )
                                                    }
                                                    required
                                                    autoComplete="off"
                                                    placeholder="acme"
                                                    aria-invalid={
                                                        !!errors.slug ||
                                                        slugInvalid
                                                    }
                                                    aria-describedby={
                                                        errors.slug
                                                            ? 'slug-error'
                                                            : 'slug-hint'
                                                    }
                                                    className="w-full min-w-0 bg-transparent px-2 py-1.5 font-mono text-sm outline-none"
                                                />
                                                <span className="flex items-center whitespace-nowrap border-input border-l bg-muted px-2 text-muted-foreground text-xs">
                                                    /login
                                                </span>
                                            </div>
                                            {errors.slug ? (
                                                <InputError
                                                    id="slug-error"
                                                    role="alert"
                                                    message={errors.slug}
                                                />
                                            ) : (
                                                <p
                                                    id="slug-hint"
                                                    className={cn(
                                                        'text-muted-foreground text-xs',
                                                        slugInvalid &&
                                                            'text-red-600 dark:text-red-400',
                                                    )}
                                                >
                                                    Lowercase letters, numbers
                                                    and single hyphens.
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="space-y-4">
                                        <p className="font-medium text-muted-foreground text-xs uppercase tracking-wide">
                                            First admin user
                                        </p>
                                        <div className="space-y-2">
                                            <Label htmlFor="admin_name">
                                                Full name
                                            </Label>
                                            <Input
                                                id="admin_name"
                                                name="admin_name"
                                                required
                                                autoComplete="off"
                                                aria-invalid={
                                                    !!errors.admin_name
                                                }
                                                aria-describedby={
                                                    errors.admin_name
                                                        ? 'admin_name-error'
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                id="admin_name-error"
                                                role="alert"
                                                message={errors.admin_name}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="admin_email">
                                                Email
                                            </Label>
                                            <Input
                                                id="admin_email"
                                                name="admin_email"
                                                type="email"
                                                required
                                                autoComplete="off"
                                                aria-invalid={
                                                    !!errors.admin_email
                                                }
                                                aria-describedby={
                                                    errors.admin_email
                                                        ? 'admin_email-error'
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                id="admin_email-error"
                                                role="alert"
                                                message={errors.admin_email}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="admin_password">
                                                Temporary password
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    id="admin_password"
                                                    name="admin_password"
                                                    type={
                                                        showPassword
                                                            ? 'text'
                                                            : 'password'
                                                    }
                                                    required
                                                    autoComplete="new-password"
                                                    aria-invalid={
                                                        !!errors.admin_password
                                                    }
                                                    aria-describedby={
                                                        errors.admin_password
                                                            ? 'admin_password-error admin_password-hint'
                                                            : 'admin_password-hint'
                                                    }
                                                    className="pr-9"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setShowPassword(
                                                            (value) => !value,
                                                        )
                                                    }
                                                    className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    aria-label={
                                                        showPassword
                                                            ? 'Hide password'
                                                            : 'Show password'
                                                    }
                                                >
                                                    {showPassword ? (
                                                        <EyeOff className="size-4" />
                                                    ) : (
                                                        <Eye className="size-4" />
                                                    )}
                                                </button>
                                            </div>
                                            <p
                                                id="admin_password-hint"
                                                className="text-muted-foreground text-xs"
                                            >
                                                Share this securely — it isn't
                                                shown again.
                                            </p>
                                            <InputError
                                                id="admin_password-error"
                                                role="alert"
                                                message={errors.admin_password}
                                            />
                                        </div>
                                    </div>
                                </div>

                                <DialogFooter className="flex-row justify-end gap-2 border-border border-t p-4">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            disabled={processing}
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <>
                                                <LoaderCircle className="size-4 animate-spin" />
                                                Creating…
                                            </>
                                        ) : (
                                            <>
                                                <Plus className="size-4" />
                                                Create tenant
                                            </>
                                        )}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleting !== null}
                onOpenChange={(next) => {
                    if (deleteProcessing) {
                        return;
                    }

                    if (!next) {
                        setDeleting(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete tenant</DialogTitle>
                        <DialogDescription>
                            Move “{deleting?.name}” to the archive? Its
                            workspace (/{deleting?.slug}) becomes inaccessible,
                            but its data and database are kept — you can restore
                            it from Archived.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={deleteProcessing}
                            onClick={() => setDeleting(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={deleteProcessing}
                            onClick={confirmDelete}
                        >
                            {deleteProcessing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Trash2 className="size-4" />
                            )}
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </CentralAdminLayout>
    );
}
