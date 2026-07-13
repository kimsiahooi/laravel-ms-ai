import { Link, router } from '@inertiajs/react';
import {
    type ColumnDef,
    flexRender,
    getCoreRowModel,
    type RowData,
    useReactTable,
} from '@tanstack/react-table';
import {
    ChevronLeft,
    ChevronRight,
    LoaderCircle,
    Search,
    SearchX,
    X,
} from 'lucide-react';
import { Fragment, type ReactNode, useEffect, useRef, useState } from 'react';
import { ExportMenu } from '@/components/export-menu';
import { PaginationLink } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
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

/**
 * Compact page-number window computed client-side (Laravel's server `onEachSide`
 * can't shrink its edge run below 4, so page 1 would still spill `1 2 3 4 5 6 …`).
 * Shows a consistent 2 / 3 / 2 shape at every position: the first 2 pages, 3 around
 * the current page, and the last 2 pages, with `'ellipsis'` between the groups. A
 * gap of exactly one page renders that page instead of an ellipsis (no `… 3` hiding
 * a single page). Groups merge when they touch, so short lists show every number.
 */
function pageWindow(current: number, last: number): Array<number | 'ellipsis'> {
    const pages = new Set<number>();
    const add = (n: number) => {
        if (n >= 1 && n <= last) pages.add(n);
    };

    add(1);
    add(2); // left edge
    add(last - 1);
    add(last); // right edge
    add(current - 1);
    add(current);
    add(current + 1); // middle

    const sorted = [...pages].sort((a, b) => a - b);
    const items: Array<number | 'ellipsis'> = [];
    let prev = 0;

    for (const page of sorted) {
        if (prev) {
            if (page - prev === 2) {
                items.push(prev + 1); // fill a single-page gap
            } else if (page - prev > 2) {
                items.push('ellipsis');
            }
        }
        items.push(page);
        prev = page;
    }

    return items;
}

/**
 * Build the href for an arbitrary page number by swapping only the `page` param on a
 * template URL that already carries the current query string (search / per_page / any
 * extra filter, via the paginator's `withQueryString()`). Laravel emits absolute URLs,
 * so `new URL` needs no base — SSR-safe (no `window`).
 */
function pageHref(template: string, page: number): string {
    const url = new URL(template);
    url.searchParams.set('page', String(page));
    return url.toString();
}

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
    rowHref?: (row: T) => string;
    /** When set, each row gets a leading expand toggle revealing this content. */
    renderExpanded?: (row: T) => ReactNode;
    /** ExportRegistry key; when set, a CSV/Excel export control is shown. */
    exportResource?: string;
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
    rowHref,
    renderExpanded,
    exportResource,
}: DataTableProps<T>) {
    const [search, setSearch] = useState(filters.search);
    const [expandedRows, setExpandedRows] = useState<Set<string>>(
        () => new Set(),
    );
    const [loading, setLoading] = useState(false);
    const searchRef = useRef<HTMLInputElement>(null);

    // `only` is constant per page but is a fresh array literal each render; hold it
    // in a ref so the debounced-search effect doesn't need it as a dependency.
    const onlyRef = useRef(only);
    onlyRef.current = only;

    // The search value DataTable itself last requested. Lets us tell an
    // externally-reset filter (e.g. a create/delete redirect clears `search`)
    // apart from our own round-trip, so we can re-sync the box without clobbering
    // a value the user is still typing.
    const requestedSearchRef = useRef(filters.search);

    // Debounced server-side search (trimmed guard so no duplicate request fires).
    useEffect(() => {
        const q = search.trim();

        if (q === filters.search) {
            return undefined;
        }

        const timer = setTimeout(() => {
            requestedSearchRef.current = q;
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

    // Re-sync the search box when the server's `filters.search` changes for a
    // reason other than our own debounced request (e.g. a create/delete redirect
    // resets it) — otherwise the stale local query would re-hide fresh data.
    useEffect(() => {
        if (filters.search !== requestedSearchRef.current) {
            requestedSearchRef.current = filters.search;
            setSearch(filters.search);
        }
    }, [filters.search]);

    const reload = {
        only,
        preserveState: true as const,
        preserveScroll: true as const,
        replace: true as const,
        onStart: () => setLoading(true),
        onFinish: () => setLoading(false),
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
        rowCount: paginator.total, // informational only — the number window is derived client-side from current_page/last_page (see pageWindow)
    });

    const hasExpand = !!renderExpanded;
    const columnCount = table.getAllLeafColumns().length + (hasExpand ? 1 : 0);

    // Client-side pager. The visible page numbers are computed from the paginator's own
    // current_page/last_page (see pageWindow — a compact 2/3/2 window that stays short at
    // every position). Per-page hrefs reuse a template URL (prev/next) that already carries
    // the active query string, so only the `page` param changes.
    const loadingClass = cn(loading && 'pointer-events-none opacity-50');
    const pageTemplate = paginator.next_page_url ?? paginator.prev_page_url;
    const windowPages = pageWindow(paginator.current_page, paginator.last_page);

    const toggleRow = (id: string) =>
        setExpandedRows((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });

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
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div className="relative w-full sm:w-64">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                ref={searchRef}
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
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
                        {exportResource && (
                            <ExportMenu
                                resource={exportResource}
                                search={filters.search}
                            />
                        )}
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
                                    {hasExpand && (
                                        <TableHead className="w-10">
                                            <span className="sr-only">
                                                Expand
                                            </span>
                                        </TableHead>
                                    )}
                                    {headerGroup.headers.map((header) => (
                                        <TableHead
                                            key={header.id}
                                            className={
                                                header.column.columnDef.meta
                                                    ?.className
                                            }
                                        >
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef
                                                          .header,
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
                                                No results match “
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
                                    </TableCell>
                                </TableRow>
                            ) : (
                                table.getRowModel().rows.map((row) => {
                                    const isExpanded = expandedRows.has(row.id);

                                    return (
                                        <Fragment key={row.id}>
                                            <TableRow
                                                data-clickable={
                                                    rowHref ? '' : undefined
                                                }
                                                className={
                                                    rowHref
                                                        ? 'cursor-pointer'
                                                        : undefined
                                                }
                                                onClick={
                                                    rowHref
                                                        ? (event) => {
                                                              if (
                                                                  window
                                                                      .getSelection()
                                                                      ?.toString()
                                                              )
                                                                  return;
                                                              if (
                                                                  event.button !==
                                                                      0 ||
                                                                  event.metaKey ||
                                                                  event.ctrlKey ||
                                                                  event.shiftKey ||
                                                                  event.altKey
                                                              )
                                                                  return;
                                                              if (
                                                                  (
                                                                      event.target as HTMLElement
                                                                  ).closest(
                                                                      'a,button,input,[role="menuitem"],[data-slot^="dropdown-menu"]',
                                                                  )
                                                              )
                                                                  return;
                                                              router.visit(
                                                                  rowHref(
                                                                      row.original,
                                                                  ),
                                                              );
                                                          }
                                                        : undefined
                                                }
                                            >
                                                {hasExpand && (
                                                    <TableCell className="w-10 align-middle">
                                                        <button
                                                            type="button"
                                                            onClick={(
                                                                event,
                                                            ) => {
                                                                event.stopPropagation();
                                                                toggleRow(
                                                                    row.id,
                                                                );
                                                            }}
                                                            aria-expanded={
                                                                isExpanded
                                                            }
                                                            aria-label={
                                                                isExpanded
                                                                    ? 'Collapse row'
                                                                    : 'Expand row'
                                                            }
                                                            className="flex size-6 items-center justify-center rounded-md text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                        >
                                                            <ChevronRight
                                                                className={cn(
                                                                    'size-4 transition-transform',
                                                                    isExpanded &&
                                                                        'rotate-90',
                                                                )}
                                                            />
                                                        </button>
                                                    </TableCell>
                                                )}
                                                {row
                                                    .getVisibleCells()
                                                    .map((cell) => (
                                                        <TableCell
                                                            key={cell.id}
                                                            className={
                                                                cell.column
                                                                    .columnDef
                                                                    .meta
                                                                    ?.className
                                                            }
                                                        >
                                                            {flexRender(
                                                                cell.column
                                                                    .columnDef
                                                                    .cell,
                                                                cell.getContext(),
                                                            )}
                                                        </TableCell>
                                                    ))}
                                            </TableRow>
                                            {hasExpand && isExpanded && (
                                                <TableRow className="hover:bg-transparent">
                                                    <TableCell
                                                        colSpan={columnCount}
                                                        className="bg-muted/30 p-0"
                                                    >
                                                        {renderExpanded?.(
                                                            row.original,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </Fragment>
                                    );
                                })
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
                            <Pagination className="mx-0 w-auto">
                                <PaginationContent>
                                    {/* Previous */}
                                    <PaginationItem>
                                        {paginator.prev_page_url ? (
                                            <PaginationLink
                                                asChild
                                                size="default"
                                                className="gap-1 px-2.5 sm:pl-2.5"
                                            >
                                                <Link
                                                    href={
                                                        paginator.prev_page_url
                                                    }
                                                    {...reload}
                                                    aria-label="Go to previous page"
                                                    tabIndex={
                                                        loading ? -1 : undefined
                                                    }
                                                    className={loadingClass}
                                                >
                                                    <ChevronLeft />
                                                    <span className="hidden sm:block">
                                                        Previous
                                                    </span>
                                                </Link>
                                            </PaginationLink>
                                        ) : (
                                            <PaginationLink
                                                aria-disabled
                                                aria-label="Go to previous page"
                                                size="default"
                                                className="pointer-events-none gap-1 px-2.5 opacity-50 sm:pl-2.5"
                                            >
                                                <ChevronLeft />
                                                <span className="hidden sm:block">
                                                    Previous
                                                </span>
                                            </PaginationLink>
                                        )}
                                    </PaginationItem>

                                    {/* Numbered 2/3/2 window (desktop only) */}
                                    {windowPages.map((page, index) =>
                                        page === 'ellipsis' ? (
                                            <PaginationItem
                                                // biome-ignore lint/suspicious/noArrayIndexKey: the window is a stable derived sequence
                                                key={`ellipsis-${index}`}
                                                className="hidden sm:flex"
                                            >
                                                <PaginationEllipsis />
                                            </PaginationItem>
                                        ) : (
                                            <PaginationItem
                                                key={page}
                                                className="hidden sm:flex"
                                            >
                                                <PaginationLink
                                                    asChild
                                                    isActive={
                                                        page ===
                                                        paginator.current_page
                                                    }
                                                    className={loadingClass}
                                                >
                                                    <Link
                                                        href={
                                                            pageTemplate
                                                                ? pageHref(
                                                                      pageTemplate,
                                                                      page,
                                                                  )
                                                                : '#'
                                                        }
                                                        {...reload}
                                                        aria-label={`Go to page ${page}`}
                                                        aria-current={
                                                            page ===
                                                            paginator.current_page
                                                                ? 'page'
                                                                : undefined
                                                        }
                                                        tabIndex={
                                                            loading
                                                                ? -1
                                                                : undefined
                                                        }
                                                    >
                                                        {page}
                                                    </Link>
                                                </PaginationLink>
                                            </PaginationItem>
                                        ),
                                    )}

                                    {/* Next */}
                                    <PaginationItem>
                                        {paginator.next_page_url ? (
                                            <PaginationLink
                                                asChild
                                                size="default"
                                                className="gap-1 px-2.5 sm:pr-2.5"
                                            >
                                                <Link
                                                    href={
                                                        paginator.next_page_url
                                                    }
                                                    {...reload}
                                                    aria-label="Go to next page"
                                                    tabIndex={
                                                        loading ? -1 : undefined
                                                    }
                                                    className={loadingClass}
                                                >
                                                    <span className="hidden sm:block">
                                                        Next
                                                    </span>
                                                    <ChevronRight />
                                                </Link>
                                            </PaginationLink>
                                        ) : (
                                            <PaginationLink
                                                aria-disabled
                                                aria-label="Go to next page"
                                                size="default"
                                                className="pointer-events-none gap-1 px-2.5 opacity-50 sm:pr-2.5"
                                            >
                                                <span className="hidden sm:block">
                                                    Next
                                                </span>
                                                <ChevronRight />
                                            </PaginationLink>
                                        )}
                                    </PaginationItem>
                                </PaginationContent>
                            </Pagination>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
