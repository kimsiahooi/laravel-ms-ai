# Server-side TanStack DataTable Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the six hand-rolled server-side list tables with one reusable `<DataTable>` built on `@tanstack/react-table` (manual/server mode) + the shadcn `Table` and `Pagination` primitives — preserving server-side pagination/search and all current behavior.

**Architecture:** TanStack owns the column-definition + rendering layer (`getCoreRowModel` only, `manualPagination`); the server keeps pagination + search via Inertia partial reloads. A generic `<DataTable<T>>` owns the search box, per-page select, shadcn pagination footer, empty/no-results states, `aria-busy`/status region, and the reload wiring. Each page provides `ColumnDef[]` + its create/edit/delete dialogs.

**Tech Stack:** `@tanstack/react-table`, shadcn/ui (Table + Pagination), Inertia v3 + React 19 + TS, Tailwind v4, Bun, Pest.

**Spec:** `docs/superpowers/specs/2026-07-05-server-side-tanstack-datatable-design.md`

**Reference:** the current `resources/js/pages/tenant/suppliers/index.tsx` is the pattern being refactored (its search/table/pagination block becomes the `<DataTable>`; its dialogs stay).

**Conventions:** Frontend: `bun run check` + `bun run types:check`; `bun run build` must pass. `resources/js/components/ui/*` is excluded from Biome formatting (raw shadcn style); `data-table.tsx` lives outside `ui/` (Biome-formatted). Pest feature tests assert Inertia props (not DOM) → they stay green through the refactor; run the relevant `--filter` per page.

---

## Task 1: Dependency + shadcn `Table` and `Pagination` primitives

**Files:**
- Modify: `package.json` / `bun.lock` (add dep)
- Create: `resources/js/components/ui/table.tsx`
- Create: `resources/js/components/ui/pagination.tsx`

- [ ] **Step 1: Add the dependency**

Run: `bun add @tanstack/react-table`
Expected: added to `dependencies` in `package.json`, `bun.lock` updated.

- [ ] **Step 2: Create `resources/js/components/ui/table.tsx`** (raw shadcn style)

```tsx
import * as React from "react"

import { cn } from "@/lib/utils"

function Table({ className, ...props }: React.ComponentProps<"table">) {
  return (
    <div
      data-slot="table-container"
      className="relative w-full overflow-x-auto"
    >
      <table
        data-slot="table"
        className={cn("w-full caption-bottom text-sm", className)}
        {...props}
      />
    </div>
  )
}

function TableHeader({ className, ...props }: React.ComponentProps<"thead">) {
  return (
    <thead
      data-slot="table-header"
      className={cn("[&_tr]:border-b", className)}
      {...props}
    />
  )
}

function TableBody({ className, ...props }: React.ComponentProps<"tbody">) {
  return (
    <tbody
      data-slot="table-body"
      className={cn("[&_tr:last-child]:border-0", className)}
      {...props}
    />
  )
}

function TableFooter({ className, ...props }: React.ComponentProps<"tfoot">) {
  return (
    <tfoot
      data-slot="table-footer"
      className={cn(
        "bg-muted/50 border-t font-medium [&>tr]:last:border-b-0",
        className
      )}
      {...props}
    />
  )
}

function TableRow({ className, ...props }: React.ComponentProps<"tr">) {
  return (
    <tr
      data-slot="table-row"
      className={cn(
        "hover:bg-muted/50 data-[state=selected]:bg-muted border-b transition-colors",
        className
      )}
      {...props}
    />
  )
}

function TableHead({ className, ...props }: React.ComponentProps<"th">) {
  return (
    <th
      data-slot="table-head"
      className={cn(
        "text-muted-foreground h-10 px-4 text-left align-middle font-medium text-xs uppercase tracking-wide whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]",
        className
      )}
      {...props}
    />
  )
}

function TableCell({ className, ...props }: React.ComponentProps<"td">) {
  return (
    <td
      data-slot="table-cell"
      className={cn(
        "px-4 py-3 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]",
        className
      )}
      {...props}
    />
  )
}

function TableCaption({ className, ...props }: React.ComponentProps<"caption">) {
  return (
    <caption
      data-slot="table-caption"
      className={cn("text-muted-foreground mt-4 text-sm", className)}
      {...props}
    />
  )
}

export {
  Table,
  TableHeader,
  TableBody,
  TableFooter,
  TableHead,
  TableRow,
  TableCell,
  TableCaption,
}
```

(Note: `TableHead`/`TableCell` padding + uppercase header are tuned to match the app's current tables — px-4/py-3, uppercase header — so the refactor is visually seamless.)

- [ ] **Step 3: Create `resources/js/components/ui/pagination.tsx`** (raw shadcn style; `buttonVariants` is exported from `button.tsx`)

```tsx
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  MoreHorizontalIcon,
} from "lucide-react"
import * as React from "react"

import { Button, buttonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"

function Pagination({ className, ...props }: React.ComponentProps<"nav">) {
  return (
    <nav
      role="navigation"
      aria-label="pagination"
      data-slot="pagination"
      className={cn("mx-auto flex w-full justify-center", className)}
      {...props}
    />
  )
}

function PaginationContent({ className, ...props }: React.ComponentProps<"ul">) {
  return (
    <ul
      data-slot="pagination-content"
      className={cn("flex flex-row items-center gap-1", className)}
      {...props}
    />
  )
}

function PaginationItem({ ...props }: React.ComponentProps<"li">) {
  return <li data-slot="pagination-item" {...props} />
}

type PaginationLinkProps = {
  isActive?: boolean
} & Pick<React.ComponentProps<typeof Button>, "size"> &
  React.ComponentProps<"a">

function PaginationLink({
  className,
  isActive,
  size = "icon",
  ...props
}: PaginationLinkProps) {
  return (
    <a
      aria-current={isActive ? "page" : undefined}
      data-slot="pagination-link"
      data-active={isActive}
      className={cn(
        buttonVariants({
          variant: isActive ? "outline" : "ghost",
          size,
        }),
        className
      )}
      {...props}
    />
  )
}

function PaginationPrevious({
  className,
  ...props
}: React.ComponentProps<typeof PaginationLink>) {
  return (
    <PaginationLink
      aria-label="Go to previous page"
      size="default"
      className={cn("gap-1 px-2.5 sm:pl-2.5", className)}
      {...props}
    >
      <ChevronLeftIcon />
      <span className="hidden sm:block">Previous</span>
    </PaginationLink>
  )
}

function PaginationNext({
  className,
  ...props
}: React.ComponentProps<typeof PaginationLink>) {
  return (
    <PaginationLink
      aria-label="Go to next page"
      size="default"
      className={cn("gap-1 px-2.5 sm:pr-2.5", className)}
      {...props}
    >
      <span className="hidden sm:block">Next</span>
      <ChevronRightIcon />
    </PaginationLink>
  )
}

function PaginationEllipsis({
  className,
  ...props
}: React.ComponentProps<"span">) {
  return (
    <span
      aria-hidden
      data-slot="pagination-ellipsis"
      className={cn("flex size-9 items-center justify-center", className)}
      {...props}
    >
      <MoreHorizontalIcon className="size-4" />
      <span className="sr-only">More pages</span>
    </span>
  )
}

export {
  Pagination,
  PaginationContent,
  PaginationLink,
  PaginationItem,
  PaginationPrevious,
  PaginationNext,
  PaginationEllipsis,
}
```

- [ ] **Step 4: Verify**

Run: `bun run types:check` → clean. `bun run build` → succeeds. (`bun run check` will leave `ui/*` unformatted, which is expected — those files are Biome-excluded.)

- [ ] **Step 5: Commit**

```bash
git add package.json bun.lock resources/js/components/ui/table.tsx resources/js/components/ui/pagination.tsx
git commit -m "feat(ui): add @tanstack/react-table + shadcn Table & Pagination primitives"
```

---

## Task 2: The reusable server-side `<DataTable>` component

**Files:**
- Create: `resources/js/components/data-table.tsx`

- [ ] **Step 1: Create `resources/js/components/data-table.tsx`**

```tsx
import { router } from '@inertiajs/react';
import {
    type ColumnDef,
    flexRender,
    getCoreRowModel,
    type RowData,
    useReactTable,
} from '@tanstack/react-table';
import { LoaderCircle, Search, SearchX, X } from 'lucide-react';
import { type ReactNode, useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

// Let columns carry responsive / alignment classes applied to <TableHead>+<TableCell>.
declare module '@tanstack/react-table' {
    interface ColumnMeta<TData extends RowData, TValue> {
        className?: string;
    }
}

export type Paginator<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

type DataTableProps<T> = {
    columns: ColumnDef<T, unknown>[];
    paginator: Paginator<T>;
    filters: { search: string; per_page: number };
    baseUrl: string;
    only: string[];
    getRowId: (row: T) => string;
    title: ReactNode;
    searchPlaceholder?: string;
    toolbar?: ReactNode;
    emptyState: ReactNode;
};

export function DataTable<T>({
    columns,
    paginator,
    filters,
    baseUrl,
    only,
    getRowId,
    title,
    searchPlaceholder = 'Search…',
    toolbar,
    emptyState,
}: DataTableProps<T>) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const searchRef = useRef<HTMLInputElement>(null);

    // `only` is constant per page but is a fresh array literal each render; hold it
    // in a ref so the debounced-search effect doesn't need it as a dependency.
    const onlyRef = useRef(only);
    onlyRef.current = only;

    // Debounced server-side search (trimmed guard so no duplicate request fires).
    useEffect(() => {
        const q = search.trim();

        if (q === filters.search) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.get(
                baseUrl,
                { search: q || undefined, per_page: filters.per_page },
                {
                    only: onlyRef.current,
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onStart: () => setLoading(true),
                    onFinish: () => setLoading(false),
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search, filters.search, filters.per_page, baseUrl]);

    const reload = {
        only,
        preserveState: true as const,
        preserveScroll: true as const,
        replace: true as const,
        onStart: () => setLoading(true),
        onFinish: () => setLoading(false),
    };

    const navigate = (url: string | null) => {
        if (!url) {
            return;
        }
        router.get(url, {}, reload);
    };

    const clearSearch = () => {
        setSearch('');
        searchRef.current?.focus();
    };

    const table = useReactTable({
        data: paginator.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getRowId,
        manualPagination: true,
        rowCount: paginator.total,
    });

    const columnCount = table.getAllLeafColumns().length;

    // First-class empty state (no rows and no active search).
    if (paginator.total === 0 && filters.search === '') {
        return <>{emptyState}</>;
    }

    return (
        <Card>
            <CardHeader className="gap-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <CardTitle>{title}</CardTitle>
                        <Badge variant="secondary" className="tabular-nums">
                            {paginator.total}
                        </Badge>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="relative w-full sm:w-64">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                ref={searchRef}
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder={searchPlaceholder}
                                aria-label="Search"
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
                        {toolbar}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <p role="status" aria-live="polite" className="sr-only">
                    {paginator.data.length > 0
                        ? `Showing ${paginator.from} to ${paginator.to} of ${paginator.total}`
                        : `No results match "${filters.search}"`}
                </p>
                <div
                    aria-busy={loading}
                    className={cn(
                        'transition-opacity',
                        loading && 'pointer-events-none opacity-60',
                    )}
                >
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <TableHead
                                            key={header.id}
                                            className={header.column.columnDef.meta?.className}
                                        >
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef.header,
                                                      header.getContext(),
                                                  )}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 ? (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={columnCount}>
                                        <div className="flex flex-col items-center gap-2 py-12 text-center">
                                            <SearchX className="size-6 text-muted-foreground" />
                                            <p className="text-muted-foreground text-sm">
                                                No results match “{filters.search}”
                                            </p>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={clearSearch}
                                            >
                                                Clear search
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                table.getRowModel().rows.map((row) => (
                                    <TableRow key={row.id}>
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell
                                                key={cell.id}
                                                className={cell.column.columnDef.meta?.className}
                                            >
                                                {flexRender(
                                                    cell.column.columnDef.cell,
                                                    cell.getContext(),
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {paginator.data.length > 0 && (
                    <div className="flex flex-col items-center justify-between gap-4 border-border border-t px-4 py-3 sm:flex-row">
                        <p className="text-muted-foreground text-sm">
                            Showing{' '}
                            <span className="font-medium text-foreground tabular-nums">
                                {paginator.from}
                            </span>
                            –
                            <span className="font-medium text-foreground tabular-nums">
                                {paginator.to}
                            </span>{' '}
                            of{' '}
                            <span className="font-medium text-foreground tabular-nums">
                                {paginator.total}
                            </span>
                        </p>
                        <div className="flex items-center gap-3">
                            <div className="flex items-center gap-2">
                                <span className="hidden text-muted-foreground text-sm sm:inline">
                                    Per page
                                </span>
                                <Select
                                    value={String(paginator.per_page)}
                                    disabled={loading}
                                    onValueChange={(value) =>
                                        router.get(
                                            baseUrl,
                                            {
                                                search:
                                                    filters.search || undefined,
                                                per_page: Number(value),
                                            },
                                            reload,
                                        )
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
                                            <SelectItem key={n} value={String(n)}>
                                                {n}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            {paginator.last_page > 1 && (
                                <Pagination className="mx-0 w-auto">
                                    <PaginationContent>
                                        {paginator.links.map((link, index) => {
                                            const disabled = !link.url || loading;
                                            const disabledClass = cn(
                                                disabled &&
                                                    'pointer-events-none opacity-50',
                                            );

                                            if (index === 0) {
                                                return (
                                                    <PaginationItem key="prev">
                                                        <PaginationPrevious
                                                            href={link.url ?? '#'}
                                                            aria-disabled={disabled}
                                                            className={disabledClass}
                                                            onClick={(event) => {
                                                                event.preventDefault();
                                                                navigate(link.url);
                                                            }}
                                                        />
                                                    </PaginationItem>
                                                );
                                            }

                                            if (
                                                index ===
                                                paginator.links.length - 1
                                            ) {
                                                return (
                                                    <PaginationItem key="next">
                                                        <PaginationNext
                                                            href={link.url ?? '#'}
                                                            aria-disabled={disabled}
                                                            className={disabledClass}
                                                            onClick={(event) => {
                                                                event.preventDefault();
                                                                navigate(link.url);
                                                            }}
                                                        />
                                                    </PaginationItem>
                                                );
                                            }

                                            if (link.label === '...') {
                                                return (
                                                    <PaginationItem
                                                        key={`ellipsis-${index}`}
                                                        className="hidden sm:flex"
                                                    >
                                                        <PaginationEllipsis />
                                                    </PaginationItem>
                                                );
                                            }

                                            return (
                                                <PaginationItem
                                                    key={link.label}
                                                    className="hidden sm:flex"
                                                >
                                                    <PaginationLink
                                                        href={link.url ?? '#'}
                                                        isActive={link.active}
                                                        className={cn(
                                                            loading &&
                                                                'pointer-events-none opacity-50',
                                                        )}
                                                        onClick={(event) => {
                                                            event.preventDefault();
                                                            navigate(link.url);
                                                        }}
                                                    >
                                                        {link.label}
                                                    </PaginationLink>
                                                </PaginationItem>
                                            );
                                        })}
                                    </PaginationContent>
                                </Pagination>
                            )}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
```

- [ ] **Step 2: Verify it compiles**

Run: `bun run check` (Biome — this file IS formatted) → 0 errors. `bun run types:check` → clean (confirms the `ColumnMeta` augmentation resolves and the generics are sound). `bun run build` → succeeds.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/data-table.tsx
git commit -m "feat(ui): reusable server-side DataTable (TanStack manual mode + shadcn)"
```

---

## Task 3: Convert Suppliers (reference conversion)

**Files:**
- Modify: `resources/js/pages/tenant/suppliers/index.tsx`

The create/edit dialog, delete dialog, page `<h1>` heading, field state, and all handlers (`openCreate`/`openEdit`/`confirmDelete`/`resetForm`/`flashToast`) **stay exactly as they are**. Only the list rendering (the `{suppliers.total === 0 ? … : <Card>…</Card>}` block) is replaced with a `<DataTable>` + a `ColumnDef` array, and the `Paginator` type gains `links`.

- [ ] **Step 1: Add imports** — add to the top of `suppliers/index.tsx`:

```tsx
import type { ColumnDef } from '@tanstack/react-table';
import { DataTable, type Paginator } from '@/components/data-table';
```

- [ ] **Step 2: Remove the local `Paginator` type** — delete the local `type Paginator<T> = { … }` block (lines ~59-69); it now comes from `@/components/data-table` (which includes `links`). Keep the `Supplier`, `PageProps` types (they reference the imported `Paginator`).

- [ ] **Step 3: Define the columns** — inside `SuppliersIndex`, above the `return`, add:

```tsx
const columns: ColumnDef<Supplier>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => (
            <span className="font-medium text-foreground">
                {row.original.name}
            </span>
        ),
    },
    {
        accessorKey: 'email',
        header: 'Email',
        cell: ({ row }) => row.original.email ?? '—',
        meta: {
            className:
                'hidden max-w-md truncate text-muted-foreground sm:table-cell',
        },
    },
    {
        accessorKey: 'phone',
        header: 'Phone',
        cell: ({ row }) => row.original.phone ?? '—',
        meta: { className: 'hidden text-muted-foreground md:table-cell' },
    },
    {
        id: 'actions',
        header: () => <span className="sr-only">Actions</span>,
        meta: { className: 'text-right' },
        cell: ({ row }) => (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        aria-label={`Actions for ${row.original.name}`}
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => openEdit(row.original)}>
                        <Pencil className="size-4" />
                        Edit
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setDeleting(row.original)}
                    >
                        <Trash2 className="size-4" />
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        ),
    },
];
```

- [ ] **Step 4: Replace the list block with `<DataTable>`** — replace the entire `{suppliers.total === 0 && filters.search === '' ? ( <Card>…empty…</Card> ) : ( <Card>…search+table+pagination…</Card> )}` block (lines ~212-490) with:

```tsx
<DataTable
    columns={columns}
    paginator={suppliers}
    filters={filters}
    baseUrl={base}
    only={['suppliers', 'filters']}
    getRowId={(supplier) => String(supplier.id)}
    title="Suppliers"
    searchPlaceholder="Search name or email…"
    toolbar={
        <Button onClick={openCreate} className="shrink-0">
            <Plus className="size-4" />
            New supplier
        </Button>
    }
    emptyState={
        <Card>
            <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                <span className="grid size-12 place-items-center rounded-xl bg-secondary text-foreground">
                    <Truck className="size-6" />
                </span>
                <div className="space-y-1">
                    <h3 className="font-semibold text-lg">No suppliers yet</h3>
                    <p className="mx-auto max-w-sm text-muted-foreground text-sm">
                        Add your first supplier to start tracking your vendors.
                    </p>
                </div>
                <Button onClick={openCreate}>
                    <Plus className="size-4" />
                    New supplier
                </Button>
            </CardContent>
        </Card>
    }
/>
```

- [ ] **Step 5: Clean up now-unused imports/code** — the DataTable now owns the search input, per-page select, pagination, and status region, so these are no longer used by the page: `CardHeader`, `CardTitle`, `Badge`, `Select*`, `Search`, `SearchX`, `X`, `ChevronLeft`, `ChevronRight`, and the `listReload` object, the `visit` helper, the debounced-search `useEffect`, the `search`/`loading` state, the `searchRef`, and `clearSearch`. Remove them. Keep: `Card`, `CardContent` (used by `emptyState`); `Button`, `Dialog*`, `DropdownMenu*`, `Input`, `Label`, `Textarea`, `LoaderCircle`, `Plus`, `Pencil`, `Trash2`, `MoreHorizontal`, `Truck`, `toast`, `flashToast`, `Form`, `Head`, `router`, `usePage`, `useState`, `TenantLayout`, `cn` — used by the heading/dialogs/columns/emptyState. Run `bun run check` — Biome's unused-imports/vars lint will flag anything missed; remove until clean.

- [ ] **Step 6: Verify**

Run: `php artisan test --filter=SupplierTest` → 7 pass (props unchanged, so the Inertia assertions still hold).
Run: `bun run check && bun run types:check && bun run build` → 0 errors / clean / build succeeds.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/tenant/suppliers/index.tsx
git commit -m "refactor(catalog): suppliers list uses the shared DataTable"
```

---

## Task 4: Convert Categories

**Files:** Modify `resources/js/pages/tenant/categories/index.tsx`

Same transformation as Task 3 (Suppliers), adapted to Categories' fields
(`{ id, name, description, created_at }`). Its create/edit + delete dialogs and
heading stay; replace the list block with `<DataTable>` + columns; import
`DataTable`/`Paginator`/`ColumnDef`; drop the local `Paginator` type; clean unused
imports.

- [ ] **Step 1: Columns** — above the return:

```tsx
const columns: ColumnDef<Category>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => (
            <span className="font-medium text-foreground">
                {row.original.name}
            </span>
        ),
    },
    {
        accessorKey: 'description',
        header: 'Description',
        cell: ({ row }) => row.original.description ?? '—',
        meta: {
            className:
                'hidden max-w-md truncate text-muted-foreground sm:table-cell',
        },
    },
    {
        id: 'actions',
        header: () => <span className="sr-only">Actions</span>,
        meta: { className: 'text-right' },
        cell: ({ row }) => (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        aria-label={`Actions for ${row.original.name}`}
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => openEdit(row.original)}>
                        <Pencil className="size-4" />
                        Edit
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setDeleting(row.original)}
                    >
                        <Trash2 className="size-4" />
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        ),
    },
];
```

- [ ] **Step 2: `<DataTable>`** — replace the list block with a `<DataTable>` using
  `paginator={categories}`, `only={['categories', 'filters']}`,
  `getRowId={(category) => String(category.id)}`, `title="Categories"`,
  `searchPlaceholder="Search name or description…"`, a "New category" toolbar
  button (`onClick={openCreate}`), and the existing categories empty-state Card as
  `emptyState` (icon `FolderTree`, "No categories yet", its copy + CTA — reuse the
  page's current empty-state markup verbatim).

- [ ] **Step 3: Imports + cleanup** — add the `DataTable`/`Paginator`/`ColumnDef`
  imports, remove the local `Paginator` type, and clean unused imports (as in Task 3
  Step 5). Run `bun run check` until clean.

- [ ] **Step 4: Verify** — `php artisan test --filter=CategoryTest` → 6 pass;
  `bun run check && bun run types:check && bun run build` → clean.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/tenant/categories/index.tsx
git commit -m "refactor(catalog): categories list uses the shared DataTable"
```

---

## Task 5: Convert Customers

**Files:** Modify `resources/js/pages/tenant/customers/index.tsx`

Identical to Suppliers (same fields). Columns Name / Email / Phone / actions — copy
the Task 3 `columns` array verbatim but typed `ColumnDef<Customer>[]`. `<DataTable>`
props: `paginator={customers}`, `only={['customers', 'filters']}`,
`getRowId={(customer) => String(customer.id)}`, `title="Customers"`,
`searchPlaceholder="Search name or email…"`, "New customer" toolbar, and the
existing customers empty-state Card (icon `Contact`) as `emptyState`. Add imports,
drop the local `Paginator` type, clean unused imports.

- [ ] **Step 1:** columns (Task 3 array, `ColumnDef<Customer>[]`, `row.original.*`).
- [ ] **Step 2:** `<DataTable>` with the customers props above; keep the create/edit + delete dialogs and heading.
- [ ] **Step 3:** imports + cleanup (`bun run check` until clean).
- [ ] **Step 4:** `php artisan test --filter=CustomerTest` → 7 pass; `bun run check && bun run types:check && bun run build` → clean.
- [ ] **Step 5:** commit `refactor(catalog): customers list uses the shared DataTable`.

---

## Task 6: Convert Raw materials

**Files:** Modify `resources/js/pages/tenant/raw-materials/index.tsx`

Fields `{ id, name, sku, unit, min_stock: string, created_at }`.

- [ ] **Step 1: Columns**

```tsx
const columns: ColumnDef<RawMaterial>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => (
            <span className="font-medium text-foreground">
                {row.original.name}
            </span>
        ),
    },
    {
        accessorKey: 'sku',
        header: 'SKU',
        cell: ({ row }) => (
            <span className="font-mono text-muted-foreground text-xs">
                {row.original.sku}
            </span>
        ),
    },
    {
        accessorKey: 'unit',
        header: 'Unit',
        cell: ({ row }) => row.original.unit,
        meta: { className: 'hidden text-muted-foreground md:table-cell' },
    },
    {
        accessorKey: 'min_stock',
        header: 'Min stock',
        cell: ({ row }) =>
            Number(row.original.min_stock).toLocaleString(undefined, {
                maximumFractionDigits: 4,
            }),
        meta: { className: 'text-right text-muted-foreground tabular-nums' },
    },
    {
        id: 'actions',
        header: () => <span className="sr-only">Actions</span>,
        meta: { className: 'text-right' },
        cell: ({ row }) => (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        aria-label={`Actions for ${row.original.name}`}
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => openEdit(row.original)}>
                        <Pencil className="size-4" />
                        Edit
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setDeleting(row.original)}
                    >
                        <Trash2 className="size-4" />
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        ),
    },
];
```

- [ ] **Step 2: `<DataTable>`** — `paginator={rawMaterials}`, `only={['rawMaterials', 'filters']}`, `getRowId={(rm) => String(rm.id)}`, `title="Raw materials"`, `searchPlaceholder="Search name or SKU…"`, "New raw material" toolbar, existing empty-state Card (icon `Boxes`) as `emptyState`. Keep dialogs + heading.
- [ ] **Step 3: Imports + cleanup** (`bun run check` until clean).
- [ ] **Step 4: Verify** — `php artisan test --filter=RawMaterialTest` → 6 pass; `bun run check && bun run types:check && bun run build` → clean.
- [ ] **Step 5: Commit** `refactor(catalog): raw materials list uses the shared DataTable`.

---

## Task 7: Convert Admin Tenants (index)

**Files:** Modify `resources/js/pages/admin/tenants/index.tsx`

The meatiest conversion: it has a create `Dialog`, a delete `Dialog`, copy-slug/URL
affordances, and per-row "Open" link. Keep ALL of those page-specific pieces
(dialogs, `useClipboard`, `handleCopy`, `deleteProcessing`, `confirmDelete`, the
create sheet/dialog). Replace only the list block with `<DataTable>` + columns.

Row shape: `{ name, slug, created_at }` (keyed by `slug`).

- [ ] **Step 1: Columns** — build `ColumnDef<Tenant>[]` from the current table cells:
  a **Tenant** column (initials avatar + name + mobile `/slug`), a **Slug** column
  (`hidden sm:table-cell`, the copy-slug badge + copy button using the existing
  `handleCopy`/`copiedSlug`), a **Created** column (`hidden md:table-cell`,
  `timeAgo(row.original.created_at)`), and an **actions** column (`text-right`) with
  the existing "Open" external link + the dropdown (Open workspace / Copy URL / Copy
  slug / Delete) — moving the current row JSX into `cell: ({ row }) => …` using
  `row.original`.
- [ ] **Step 2: `<DataTable>`** — `paginator={tenants}`, `filters={filters}`,
  `only={['tenants', 'filters']}`, `getRowId={(tenant) => tenant.slug}`,
  `title="Tenants"`, `searchPlaceholder="Search name or slug…"`, the existing "New
  tenant" toolbar button, and the existing "No tenants yet" empty-state Card as
  `emptyState`. Note the stats-only dashboard is separate — this page is just the
  list. Keep the create + delete dialogs.
- [ ] **Step 3: Imports + cleanup** — add `DataTable`/`Paginator`/`ColumnDef`, drop
  the local `Paginator` type, remove the now-unused search/pagination/table imports
  and helpers (as Task 3 Step 5). `useClipboard`, `useInitials`, `timeAgo`,
  `absoluteDate`, the dialogs, and their state stay. `bun run check` until clean.
- [ ] **Step 4: Verify** — `php artisan test --filter="TenantIndex|TenantManagement"` → pass; `bun run check && bun run types:check && bun run build` → clean.
- [ ] **Step 5: Commit** `refactor(admin): tenants index uses the shared DataTable`.

---

## Task 8: Convert Admin Tenants (trashed / Archived)

**Files:** Modify `resources/js/pages/admin/tenants/trashed.tsx`

Keep the Restore dialog, the type-to-confirm force-delete dialog, the `processing`
state, `confirmRestore`/`confirmPurge`/`closePurge`, and the header "Back to
tenants" link. Replace the list block with `<DataTable>` + columns. Row shape
`{ name, slug, deleted_at }` (keyed by `slug`).

- [ ] **Step 1: Columns** — `ColumnDef<TrashedTenant>[]`: **Tenant** (initials +
  name + mobile `/slug`), **Slug** (`hidden sm:table-cell`, the `/slug` badge),
  **Deleted** (`hidden md:table-cell`, `timeAgo(row.original.deleted_at)`), and an
  **actions** column with the existing Restore button + dropdown (Restore / Delete
  permanently + the mobile "Deleted …" line), wired to `setRestoring`/`setPurging`.
- [ ] **Step 2: `<DataTable>`** — `paginator={tenants}`, `only={['tenants', 'filters']}`,
  `getRowId={(tenant) => tenant.slug}`, `title="Archived"`,
  `searchPlaceholder="Search name or slug…"`, no create toolbar (Archived has none —
  omit `toolbar`), and the existing "Archive is empty" empty-state Card as
  `emptyState`. Keep the restore + force-delete dialogs and the header.
- [ ] **Step 3: Imports + cleanup** (`bun run check` until clean).
- [ ] **Step 4: Verify** — `php artisan test --filter=TenantLifecycleTest` → 8 pass; `bun run check && bun run types:check && bun run build` → clean.
- [ ] **Step 5: Commit** `refactor(admin): archived tenants list uses the shared DataTable`.

---

## Task 9: Full verification

**Files:** none.

- [ ] **Step 1: Backend suite** — `php artisan test --compact` → all pass (the refactor changed no props/routes, so 102/102 stay green).
- [ ] **Step 2: Frontend gates** — `bun run check:ci` (0 errors), `bun run types:check` (clean), `bun run build` (succeeds).
- [ ] **Step 3: Confirm no raw `<table>` remains in list pages** — `grep -rn "<table" resources/js/pages` should return nothing (all six now use `<DataTable>`).
- [ ] **Step 4: (Optional) manual smoke** — for one tenant list and the admin tenants pages: search, per-page change, page through with the shadcn pager, empty + no-results states, and row actions/dialogs all work.

---

## Self-review coverage map

- Spec "Dependency" + "ui/table.tsx" + "ui/pagination.tsx" → **Task 1**.
- Spec "data-table.tsx" (TanStack manual mode, chrome, states, shadcn Pagination via `links`) → **Task 2**.
- Spec "Column API" (`ColumnDef` + `meta.className` + actions column) → Tasks 3–8 column arrays.
- Spec "What stays on each page" (dialogs, toolbar, emptyState) → each conversion keeps dialogs, passes toolbar/emptyState.
- Spec "Scope & order" (Suppliers first, then the five) → Tasks 3 → 4–8.
- Spec "Pagination = shadcn `Pagination` rendering `links` with Inertia nav" → Task 2 pagination block; `Paginator.links` added in Task 2's `Paginator` type + Task 3 Step 2 drops the local one.
- Spec "Testing" (Pest props unaffected; frontend gates) → per-task verify steps + Task 9.
- Spec "server-side wiring unchanged" → no backend task; `only`/`baseUrl`/paginator meta preserved in the DataTable.
