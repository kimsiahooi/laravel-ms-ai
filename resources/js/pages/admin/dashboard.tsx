import { Form, Head, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarPlus,
    CalendarRange,
    Check,
    Clock,
    Copy,
    ExternalLink,
    Eye,
    EyeOff,
    LoaderCircle,
    MoreHorizontal,
    Plus,
    Search,
    SearchX,
    X,
} from 'lucide-react';
import {
    type ComponentType,
    type ReactNode,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';
import { useInitials } from '@/hooks/use-initials';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import { cn } from '@/lib/utils';

type Tenant = {
    name: string;
    slug: string;
    created_at: string;
};

type PageProps = {
    auth: { user: { name: string; email: string } | null };
    tenants: Tenant[];
    flash: { success: string | null };
};

const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const RELATIVE_TIME = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFKD')
        .replace(/\p{M}/gu, '') // strip combining marks left by NFKD
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/** Best-effort relative timestamp; render-time snapshot (does not tick live). */
function timeAgo(iso: string): string {
    const then = new Date(iso).getTime();

    if (Number.isNaN(then)) {
        return '';
    }

    const seconds = Math.round((then - Date.now()) / 1000);
    const abs = Math.abs(seconds);

    if (abs < 60) {
        return 'just now';
    }

    const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['minute', 60],
        ['hour', 3600],
        ['day', 86400],
        ['month', 2592000],
        ['year', 31536000],
    ];

    let chosen: [Intl.RelativeTimeFormatUnit, number] = ['minute', 60];

    for (const unit of units) {
        if (abs >= unit[1]) {
            chosen = unit;
        }
    }

    return RELATIVE_TIME.format(Math.round(seconds / chosen[1]), chosen[0]);
}

function absoluteDate(iso: string): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

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
    const { auth, tenants } = usePage().props as unknown as PageProps;
    const getInitials = useInitials();
    const [, copy] = useClipboard();

    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [name, setName] = useState('');
    const [slug, setSlug] = useState('');
    const [slugTouched, setSlugTouched] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [copiedSlug, setCopiedSlug] = useState<string | null>(null);
    const [greeting, setGreeting] = useState('Welcome back');

    const searchRef = useRef<HTMLInputElement>(null);
    const copyTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const origin = typeof window === 'undefined' ? '' : window.location.origin;
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

    useEffect(
        () => () => {
            if (copyTimer.current) {
                clearTimeout(copyTimer.current);
            }
        },
        [],
    );

    const stats = useMemo(() => {
        const now = new Date();
        const startOfToday = new Date(
            now.getFullYear(),
            now.getMonth(),
            now.getDate(),
        ).getTime();
        const sevenDaysAgo = now.getTime() - 7 * 24 * 60 * 60 * 1000;

        let addedToday = 0;
        let last7 = 0;

        for (const tenant of tenants) {
            const created = new Date(tenant.created_at).getTime();

            if (created >= startOfToday) {
                addedToday += 1;
            }

            if (created >= sevenDaysAgo) {
                last7 += 1;
            }
        }

        return {
            total: tenants.length,
            addedToday,
            last7,
            newest: tenants[0] ?? null,
        };
    }, [tenants]);

    const normalizedQuery = query.trim().toLowerCase();
    const filtered = useMemo(() => {
        if (normalizedQuery === '') {
            return tenants;
        }

        return tenants.filter((tenant) =>
            `${tenant.name} ${tenant.slug}`
                .toLowerCase()
                .includes(normalizedQuery),
        );
    }, [tenants, normalizedQuery]);

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

    const resetForm = () => {
        setName('');
        setSlug('');
        setSlugTouched(false);
        setShowPassword(false);
    };

    const clearSearch = () => {
        setQuery('');
        searchRef.current?.focus();
    };

    return (
        <CentralAdminLayout>
            <Head title="Admin" />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Dashboard
                </h1>
                <p
                    className="text-muted-foreground text-sm"
                    suppressHydrationWarning
                >
                    {greeting}, {firstName}. Provision and oversee every tenant
                    workspace.
                </p>
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
                    value={
                        <span suppressHydrationWarning>{stats.addedToday}</span>
                    }
                    sub="since midnight (your time)"
                />
                <StatCard
                    icon={CalendarRange}
                    label="Last 7 days"
                    value={<span suppressHydrationWarning>{stats.last7}</span>}
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

            {tenants.length === 0 ? (
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
                                Provision your first workspace to get started.
                                Each tenant gets an isolated database and its
                                own login URL.
                            </p>
                        </div>
                        <Button onClick={() => setOpen(true)}>
                            <Plus className="size-4" />
                            Create your first tenant
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardHeader className="gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <CardTitle>Tenants</CardTitle>
                                <Badge
                                    variant="secondary"
                                    className="tabular-nums"
                                >
                                    {normalizedQuery === ''
                                        ? tenants.length
                                        : `${filtered.length} of ${tenants.length}`}
                                </Badge>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="relative w-full sm:w-64">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        ref={searchRef}
                                        value={query}
                                        onChange={(event) =>
                                            setQuery(event.target.value)
                                        }
                                        placeholder="Search name or slug…"
                                        aria-label="Search tenants"
                                        className="px-9"
                                    />
                                    {query !== '' && (
                                        <button
                                            type="button"
                                            onClick={clearSearch}
                                            aria-label="Clear search"
                                            className="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    )}
                                </div>
                                <Button
                                    onClick={() => setOpen(true)}
                                    className="shrink-0"
                                >
                                    <Plus className="size-4" />
                                    New tenant
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
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
                                            Created
                                        </th>
                                        <th className="h-10 px-4 text-right">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.length === 0 ? (
                                        <tr>
                                            <td colSpan={4}>
                                                <div className="flex flex-col items-center gap-2 py-12 text-center">
                                                    <SearchX className="size-6 text-muted-foreground" />
                                                    <p className="text-muted-foreground text-sm">
                                                        No tenants match “
                                                        {query.trim()}”
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
                                        filtered.map((tenant) => {
                                            const loginPath = `/${tenant.slug}/login`;
                                            const copied =
                                                copiedSlug === tenant.slug;

                                            return (
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
                                                                    {
                                                                        tenant.name
                                                                    }
                                                                </p>
                                                                <p className="truncate font-mono text-muted-foreground text-xs sm:hidden">
                                                                    /
                                                                    {
                                                                        tenant.slug
                                                                    }
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="hidden px-4 py-3 sm:table-cell">
                                                        <div className="flex items-center gap-1.5">
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    handleCopy(
                                                                        tenant.slug,
                                                                        tenant.slug,
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
                                                                    /
                                                                    {
                                                                        tenant.slug
                                                                    }
                                                                </Badge>
                                                            </button>
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-7"
                                                                        onClick={() =>
                                                                            handleCopy(
                                                                                tenant.slug,
                                                                                tenant.slug,
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
                                                                    {copied
                                                                        ? 'Copied'
                                                                        : 'Copy slug'}
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </div>
                                                    </td>
                                                    <td className="hidden px-4 py-3 md:table-cell">
                                                        <span
                                                            className="whitespace-nowrap text-muted-foreground tabular-nums"
                                                            title={absoluteDate(
                                                                tenant.created_at,
                                                            )}
                                                            suppressHydrationWarning
                                                        >
                                                            {timeAgo(
                                                                tenant.created_at,
                                                            )}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Button
                                                                asChild
                                                                variant="outline"
                                                                size="sm"
                                                                className="hidden sm:inline-flex"
                                                            >
                                                                <a
                                                                    href={
                                                                        loginPath
                                                                    }
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                >
                                                                    <ExternalLink className="size-3.5" />
                                                                    Open
                                                                </a>
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
                                                                        asChild
                                                                        className="sm:hidden"
                                                                    >
                                                                        <a
                                                                            href={
                                                                                loginPath
                                                                            }
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                        >
                                                                            <ExternalLink className="size-4" />
                                                                            Open
                                                                            workspace
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
                                                                        Copy
                                                                        workspace
                                                                        URL
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
                                                                        Copy
                                                                        slug
                                                                    </DropdownMenuItem>
                                                                    <div
                                                                        className="px-2 py-1.5 text-muted-foreground text-xs md:hidden"
                                                                        suppressHydrationWarning
                                                                    >
                                                                        Created{' '}
                                                                        {timeAgo(
                                                                            tenant.created_at,
                                                                        )}
                                                                    </div>
                                                                </DropdownMenuContent>
                                                            </DropdownMenu>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

            <Sheet
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
                <SheetContent
                    side="right"
                    className="w-full gap-0 p-0 sm:max-w-md"
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
                    <SheetHeader className="gap-3 border-border border-b">
                        <span className="grid size-9 place-items-center rounded-lg bg-secondary text-foreground">
                            <Building2 className="size-5" />
                        </span>
                        <div className="space-y-1">
                            <SheetTitle>Create a tenant</SheetTitle>
                            <SheetDescription>
                                Provision an isolated workspace and seed its
                                first admin user.
                            </SheetDescription>
                        </div>
                    </SheetHeader>

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
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="flex-1 space-y-6 overflow-y-auto px-4 py-5">
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

                                <SheetFooter className="flex-row justify-end gap-2 border-border border-t">
                                    <SheetClose asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            disabled={processing}
                                        >
                                            Cancel
                                        </Button>
                                    </SheetClose>
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
                                </SheetFooter>
                            </>
                        )}
                    </Form>
                </SheetContent>
            </Sheet>
        </CentralAdminLayout>
    );
}
