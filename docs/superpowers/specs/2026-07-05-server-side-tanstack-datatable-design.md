# Server-side TanStack DataTable — design

**Date:** 2026-07-05
**Status:** Approved (pending spec review)
**Area:** Shared frontend (admin + tenant list pages)

## Goal

The six list pages — `admin/tenants/index`, `admin/tenants/trashed`,
`tenant/{categories,suppliers,customers,raw-materials}/index` — each hand-roll the
same raw `<table>` + search + per-page + pagination + empty/no-results + status
region (~4,000 lines total, heavily duplicated). Replace that with **one reusable
`<DataTable>`** built on `@tanstack/react-table`, keeping the existing
**server-side** pagination/search (Laravel paginator + Inertia partial reloads).

Chosen approach (**B**): TanStack in **manual/server mode** — TanStack owns the
column-definition + rendering layer; the server still owns pagination and search.
Not adding client-side sorting, filtering, column-visibility, or row selection in
this cycle (foundation is laid for server-side sorting later).

## Non-goals (this cycle)

- Client-side fetching/pagination/sorting/filtering (deliberately server-side).
- Sortable column headers, column show/hide, row selection, bulk actions.
- Changing any backend controller, route, validation, or the paginator shape.

## Dependency

Add `@tanstack/react-table` (runtime): `bun add @tanstack/react-table`.

## Components

### `resources/js/components/ui/table.tsx` — shadcn `Table` primitive (new)

The standard shadcn Table primitive: `Table`, `TableHeader`, `TableBody`,
`TableFooter`, `TableHead`, `TableRow`, `TableCell`, `TableCaption`. Raw shadcn-CLI
output style (this dir is excluded from Biome formatting via `biome.json`).

### `resources/js/components/ui/pagination.tsx` — shadcn `Pagination` primitive (new)

The standard shadcn Pagination primitive: `Pagination`, `PaginationContent`,
`PaginationItem`, `PaginationLink`, `PaginationPrevious`, `PaginationNext`,
`PaginationEllipsis`. Raw shadcn-CLI style (same `ui/` exclusion).

### `resources/js/components/data-table.tsx` — reusable server-side DataTable (new)

A generic `DataTable<T>` (our code, Biome-formatted). Uses `useReactTable` in
manual mode and renders through the shadcn Table primitive.

```ts
// TanStack column meta augmentation so columns can carry responsive/alignment classes.
declare module '@tanstack/react-table' {
    interface ColumnMeta<TData extends RowData, TValue> {
        className?: string;      // applied to <TableHead> AND <TableCell>
        headClassName?: string;  // header-only override (optional)
    }
}

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
    // Laravel's rendered page-link window (with ellipsis). Already in the Inertia
    // props — the paginator serializes it; the frontend just wasn't using it.
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type DataTableProps<T> = {
    columns: ColumnDef<T, unknown>[];
    paginator: Paginator<T>;
    filters: { search: string; per_page: number };
    baseUrl: string;                 // e.g. `/${slug}/suppliers`
    only: string[];                  // Inertia partial-reload keys, e.g. ['suppliers','filters']
    getRowId: (row: T) => string;    // stable key (id or slug)
    title: ReactNode;                // card title (e.g. "Suppliers")
    searchPlaceholder?: string;
    toolbar?: ReactNode;             // right-of-search slot (the "New …" button)
    emptyState: ReactNode;           // shown when total === 0 && no active search
};
```

The DataTable renders the whole card so it's a drop-in: a `<Card>` with a
`<CardHeader>` (the `title` + a `total`-count `Badge` on the left; the search input
+ `toolbar` on the right) and `<CardContent>` (the table + the pagination footer).
The page keeps its own page-level `<h1>` + description **above** the DataTable.

TanStack config (manual mode — no client engines):

```ts
const table = useReactTable({
    data: paginator.data,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getRowId,
    manualPagination: true,
    rowCount: paginator.total,
});
```

`getCoreRowModel` is the only row model — no `getPaginationRowModel` /
`getSortedRowModel` / `getFilteredRowModel`, so TanStack never slices, sorts, or
filters; it just renders the current server page.

The DataTable **owns the shared chrome** (all currently duplicated per page):
- **Debounced (350 ms) search** input with a trimmed guard against `filters.search`
  → `router.get(baseUrl, { search: q || undefined, per_page: filters.per_page }, reload)`.
- **Per-page `Select`** (`[10,25,50,100]`) → `router.get(baseUrl, { search, per_page: n }, reload)`.
- **Pagination footer** — "Showing {from}–{to} of {total}" text + the shadcn
  `Pagination` component rendering `paginator.links` (Laravel's page window +
  ellipsis): the first link → `PaginationPrevious`, the last → `PaginationNext`, a
  `label === '...'` link → `PaginationEllipsis`, the rest → `PaginationLink
  isActive={link.active}`. Each clickable link **navigates via Inertia**, not a raw
  anchor: `onClick` → `router.get(link.url, {}, reload)` with `preventDefault`;
  links with `url === null` (current page, ellipsis, disabled prev/next) are inert.
  This keeps the partial-reload + loading behavior while using shadcn styling.
- **Loading** — internal `loading` state via the reload's `onStart`/`onFinish`;
  `aria-busy` + dimmed overlay on the table container; per-page/prev/next disabled
  while loading.
- **`sr-only role="status" aria-live="polite"`** always-mounted region announcing
  "Showing X–Y of Z" or "No results match …".
- **Empty vs no-results**:
  - `paginator.total === 0 && filters.search === ''` → render the `emptyState` slot
    (a first-class panel) instead of the table.
  - Otherwise render the table; if `paginator.data.length === 0` (a search miss),
    render a single in-table "No results match "{search}"" row with a **Clear
    search** button.

Reload options (shared): `{ only, preserveState: true, preserveScroll: true, replace: true, onStart, onFinish }`.

## Column API (per page)

Each page provides standard TanStack `ColumnDef<T>[]`:
- Data columns: `{ accessorKey, header, cell: ({ row }) => … }`. Null display (e.g.
  `email ?? '—'`) lives in the `cell`.
- Responsive hide / alignment via `meta`: `{ meta: { className: 'hidden sm:table-cell' } }`,
  `{ meta: { className: 'text-right tabular-nums' } }`. The DataTable applies
  `column.columnDef.meta?.className` to both `<TableHead>` and `<TableCell>`.
- **Row actions** are just a trailing column: `{ id: 'actions', header: () => <span className="sr-only">Actions</span>, cell: ({ row }) => <RowActions row={row.original} /> }`,
  where `RowActions` is the page's existing dropdown (Edit/Delete, or Restore/Force
  for trashed tenants), calling the page's handlers.

## What stays on each page

- Page-specific **dialogs**: create/edit `<Form>` dialog, delete-confirm dialog,
  and for admin tenants: restore + type-to-confirm force-delete dialogs, plus the
  copy-slug/URL affordances.
- Field state, `openCreate`/`openEdit`, `confirmDelete`, etc.
- The `ColumnDef[]` array (incl. the actions column) and the `emptyState`/`toolbar`
  slots passed to `<DataTable>`.

Net: a page like Suppliers goes from ~730 lines to ~250 (dialogs + column defs).

## Server-side wiring (unchanged data flow)

No backend change. Controllers already return `{ <entity>: LengthAwarePaginator, filters }`.
The DataTable reads `paginator.*` for meta and `paginator.data` for rows, and issues
the same partial reloads the pages do today (`only: [entityKey, 'filters']`). Search
and per-page remain server query params; pagination follows the paginator's
`*_page_url`.

## Scope & order

All six lists. Build order:
1. `components/ui/table.tsx` + `components/ui/pagination.tsx` (primitives) +
   `components/data-table.tsx` (+ the `@tanstack/react-table` dep).
2. Convert **Suppliers** first as the reference (validate the abstraction end-to-end).
3. Roll out: Categories, Customers, Raw materials, Admin Tenants index, Admin Tenants trashed.

The admin-tenants pages are the meatier conversions (more page-specific dialogs +
copy affordances) but use the same `<DataTable>` + `ColumnDef` shape.

## Testing

- **Pest feature tests are unaffected** — they assert Inertia *props* (component
  name, `data` counts, `filters`), not table DOM. They stay green throughout,
  proving behavior (server pagination/search/soft-delete) is preserved by the
  refactor. Run the relevant `--filter` per converted page.
- **Frontend gates** after each conversion: `bun run check` / `check:ci` (0 errors),
  `bun run types:check`, `bun run build`.
- Manual smoke (optional): each list still searches, paginates, changes per-page,
  shows empty + no-results, and its row actions/dialogs work.

## Risks / notes

- `biome.json` excludes `resources/js/components/ui` from formatting, so
  `ui/table.tsx` is raw shadcn style; `data-table.tsx` lives outside `ui/` and is
  Biome-formatted.
- The `ColumnMeta` module augmentation must be declared once (in `data-table.tsx`)
  and picked up project-wide by `tsc` — verify types resolve after adding it.
- Keep the DataTable presentational + navigation-only; all mutations (delete/
  restore/force/create) stay in the page dialogs so the component has one clear
  responsibility (render + server-side list navigation).
