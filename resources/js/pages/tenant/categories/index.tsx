import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    FolderTree,
    LoaderCircle,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    SearchX,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import TenantLayout from '@/layouts/tenant-layout';
import { cn } from '@/lib/utils';

type Category = {
    id: number;
    name: string;
    description: string | null;
    created_at: string;
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
    categories: Paginator<Category>;
    filters: { search: string; per_page: number };
    tenant: { slug: string; name: string };
    flash: { success: string | null };
};

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

function flashToast(page: { props: unknown }): void {
    const message = (page.props as { flash?: { success?: string | null } })
        .flash?.success;
    if (message) {
        toast.success(message);
    }
}

export default function CategoriesIndex() {
    const page = usePage();
    const { categories, filters, tenant } = page.props as unknown as PageProps;
    const base = `/${tenant.slug}/categories`;

    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Category | null>(null);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [deleting, setDeleting] = useState<Category | null>(null);

    const searchRef = useRef<HTMLInputElement>(null);

    const listReload = {
        only: ['categories', 'filters'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => setLoading(true),
        onFinish: () => setLoading(false),
    };

    // Debounced server-side search (trimmed guard so no duplicate fires). Options
    // are inlined (not `listReload`) so the effect's dep array stays exhaustive.
    useEffect(() => {
        const q = search.trim();

        if (q === filters.search) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.get(
                base,
                { search: q || undefined, per_page: filters.per_page },
                {
                    only: ['categories', 'filters'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onStart: () => setLoading(true),
                    onFinish: () => setLoading(false),
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search, filters.search, filters.per_page, base]);

    const visit = (
        url: string | null,
        data: Record<string, string | number | undefined> = {},
    ) => {
        if (url === null) {
            return;
        }
        router.get(url, data, listReload);
    };

    const openCreate = () => {
        setEditing(null);
        setName('');
        setDescription('');
        setFormOpen(true);
    };

    const openEdit = (category: Category) => {
        setEditing(category);
        setName(category.name);
        setDescription(category.description ?? '');
        setFormOpen(true);
    };

    const clearSearch = () => {
        setSearch('');
        searchRef.current?.focus();
    };

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }
        router.delete(`${base}/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: (deleted) => {
                setDeleting(null);
                flashToast(deleted);
            },
        });
    };

    return (
        <TenantLayout
            breadcrumbs={[
                { title: 'Dashboard', href: `/${tenant.slug}/dashboard` },
                { title: 'Categories', href: base },
            ]}
        >
            <Head title="Categories" />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Categories
                </h1>
                <p className="text-muted-foreground text-sm">
                    Group the products in your catalog.
                </p>
            </div>

            {categories.total === 0 && filters.search === '' ? (
                <Card>
                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                        <span className="grid size-12 place-items-center rounded-xl bg-secondary text-foreground">
                            <FolderTree className="size-6" />
                        </span>
                        <div className="space-y-1">
                            <h3 className="font-semibold text-lg">
                                No categories yet
                            </h3>
                            <p className="mx-auto max-w-sm text-muted-foreground text-sm">
                                Create your first category to start organizing
                                your products.
                            </p>
                        </div>
                        <Button onClick={openCreate}>
                            <Plus className="size-4" />
                            New category
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardHeader className="gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <CardTitle>Categories</CardTitle>
                                <Badge
                                    variant="secondary"
                                    className="tabular-nums"
                                >
                                    {categories.total}
                                </Badge>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="relative w-full sm:w-64">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        ref={searchRef}
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Search categories…"
                                        aria-label="Search categories"
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
                                <Button
                                    onClick={openCreate}
                                    className="shrink-0"
                                >
                                    <Plus className="size-4" />
                                    New category
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <p role="status" aria-live="polite" className="sr-only">
                            {categories.data.length > 0
                                ? `Showing ${categories.from} to ${categories.to} of ${categories.total} categories`
                                : `No categories match "${filters.search}"`}
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
                                            Name
                                        </th>
                                        <th className="hidden h-10 px-4 text-left font-medium text-muted-foreground text-xs uppercase tracking-wide sm:table-cell">
                                            Description
                                        </th>
                                        <th className="h-10 px-4 text-right">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {categories.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={3}>
                                                <div className="flex flex-col items-center gap-2 py-12 text-center">
                                                    <SearchX className="size-6 text-muted-foreground" />
                                                    <p className="text-muted-foreground text-sm">
                                                        No categories match “
                                                        {filters.search}”
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
                                        categories.data.map((category) => (
                                            <tr
                                                key={category.id}
                                                className="border-border border-b transition-colors last:border-0 hover:bg-muted/50"
                                            >
                                                <td className="px-4 py-3 font-medium text-foreground">
                                                    {category.name}
                                                </td>
                                                <td className="hidden max-w-md truncate px-4 py-3 text-muted-foreground sm:table-cell">
                                                    {category.description ??
                                                        '—'}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8"
                                                                aria-label={`Actions for ${category.name}`}
                                                            >
                                                                <MoreHorizontal className="size-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    openEdit(
                                                                        category,
                                                                    )
                                                                }
                                                            >
                                                                <Pencil className="size-4" />
                                                                Edit
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                onSelect={() =>
                                                                    setDeleting(
                                                                        category,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="size-4" />
                                                                Delete
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {categories.data.length > 0 && (
                            <div className="flex flex-col items-center justify-between gap-4 border-border border-t px-4 py-3 sm:flex-row">
                                <p className="text-muted-foreground text-sm">
                                    Showing{' '}
                                    <span className="font-medium text-foreground tabular-nums">
                                        {categories.from}
                                    </span>
                                    –
                                    <span className="font-medium text-foreground tabular-nums">
                                        {categories.to}
                                    </span>{' '}
                                    of{' '}
                                    <span className="font-medium text-foreground tabular-nums">
                                        {categories.total}
                                    </span>{' '}
                                    categories
                                </p>
                                <div className="flex items-center gap-3">
                                    <div className="flex items-center gap-2">
                                        <span className="hidden text-muted-foreground text-sm sm:inline">
                                            Per page
                                        </span>
                                        <Select
                                            value={String(categories.per_page)}
                                            disabled={loading}
                                            onValueChange={(value) =>
                                                visit(base, {
                                                    search:
                                                        filters.search ||
                                                        undefined,
                                                    per_page: Number(value),
                                                })
                                            }
                                        >
                                            <SelectTrigger
                                                className="h-8 w-17"
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
                                                !categories.prev_page_url ||
                                                loading
                                            }
                                            onClick={() =>
                                                visit(categories.prev_page_url)
                                            }
                                            aria-label="Previous page"
                                        >
                                            <ChevronLeft className="size-4" />
                                        </Button>
                                        <span className="px-1 text-muted-foreground text-sm tabular-nums">
                                            Page {categories.current_page} of{' '}
                                            {categories.last_page}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-8"
                                            disabled={
                                                !categories.next_page_url ||
                                                loading
                                            }
                                            onClick={() =>
                                                visit(categories.next_page_url)
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

            {/* Create / edit dialog */}
            <Dialog
                open={formOpen}
                onOpenChange={(next) => {
                    if (!next) {
                        setFormOpen(false);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit category' : 'New category'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Update this category.'
                                : 'Add a category to organize your products.'}
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        key={editing?.id ?? 'new'}
                        action={editing ? `${base}/${editing.id}` : base}
                        method={editing ? 'put' : 'post'}
                        disableWhileProcessing
                        onSuccess={(saved) => {
                            setFormOpen(false);
                            flashToast(saved);
                        }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        value={name}
                                        onChange={(event) =>
                                            setName(event.target.value)
                                        }
                                        required
                                        autoFocus
                                        placeholder="e.g. Fasteners"
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
                                    <Label htmlFor="description">
                                        Description{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (optional)
                                        </span>
                                    </Label>
                                    <Input
                                        id="description"
                                        name="description"
                                        value={description}
                                        onChange={(event) =>
                                            setDescription(event.target.value)
                                        }
                                        placeholder="Short description"
                                        aria-invalid={!!errors.description}
                                        aria-describedby={
                                            errors.description
                                                ? 'description-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="description-error"
                                        role="alert"
                                        message={errors.description}
                                    />
                                </div>
                                <DialogFooter>
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
                                                Saving…
                                            </>
                                        ) : editing ? (
                                            'Save changes'
                                        ) : (
                                            'Create category'
                                        )}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            {/* Delete confirmation */}
            <Dialog
                open={deleting !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        setDeleting(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete category</DialogTitle>
                        <DialogDescription>
                            Remove “{deleting?.name}” from your catalog?
                            Products keep their data but lose this category.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setDeleting(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmDelete}
                        >
                            <Trash2 className="size-4" />
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </TenantLayout>
    );
}
