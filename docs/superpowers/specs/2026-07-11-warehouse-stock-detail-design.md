# Per-warehouse reorder levels + warehouse stock detail — design

**Status:** approved-pending-review · **Date:** 2026-07-11

## Goal

Give each **warehouse** its own reorder threshold per item, and a **warehouse
detail page** to (a) see what stock is currently held there and (b) set those
thresholds. This **replaces** the single global item-level `min_stock` (which lived
on the product / raw-material) with a per-`(warehouse, item)` threshold, and
redefines the dashboard's low-stock KPI to aggregate the new per-warehouse alerts.

Today the Warehouses page is CRUD-only; on-hand lives in `warehouse_stocks`;
`min_stock` is one global number per item. After this change, "is this low?" is
answered per warehouse.

## Decisions (locked with the user)

- **Fully per-warehouse.** Remove item-level `min_stock` entirely. Each
  `(warehouse, item)` has its own threshold, default **0 = no alert**.
- **Thresholds are set on the warehouse detail page**, inline-edited per row.
- **Detail page defaults to in-stock items**, with an **All items** toggle
  (products + raw materials, incl. 0 on-hand) so a threshold can be set for
  something not yet stocked there.
- **Storage: a dedicated `warehouse_reorder_levels` table** — reorder *policy* is
  kept out of the StockService-owned `warehouse_stocks` *ledger*, and a threshold
  can exist with no stock row.
- **Reorder flag is per-warehouse:** `on_hand@wh < min@wh` (and `min > 0`). The
  dashboard aggregates exactly these per-warehouse alerts, so the two never
  disagree (the global-vs-local contradiction of the previous design is gone).
- **Entry point:** clicking a warehouse **row** navigates to its detail page;
  Edit/Delete stay in the `⋯` menu.
- **Quick actions** (Adjust / Transfer) deep-link to those pages with the warehouse
  pre-filled and the create dialog auto-opened.

## Schema (pre-launch — migrations edited in place, user rolls back + reseeds)

- **New tenant table** `warehouse_reorder_levels`
  (`database/migrations/tenant/…_create_warehouse_reorder_levels_table.php`):
  | Column | Type |
  |---|---|
  | `id` | pk |
  | `warehouse_id` | FK → warehouses, **cascadeOnDelete** (drop policy with the warehouse) |
  | `stockable_type` / `stockable_id` | `morphs('stockable')` (aliases `product` / `raw_material`) |
  | `min_stock` | `decimal(15, 4)` default `0` |
  | timestamps | |
  | | `unique(['warehouse_id', 'stockable_type', 'stockable_id'])` |
- **Remove `min_stock`** from:
  - `database/migrations/tenant/…_create_products_table.php` (drop
    `unsignedInteger('min_stock')`).
  - `database/migrations/tenant/…_create_raw_materials_table.php` (drop
    `decimal('min_stock', 12, 4)`).
- Migration order: `warehouse_reorder_levels` must be created **after**
  `warehouses`, `products`, and `raw_materials` (all already earlier). Pick a
  timestamp after `create_warehouse_stocks_table`.

## Models & relationships

- **New** `WarehouseReorderLevel` — `#[Fillable(['warehouse_id','stockable_type','stockable_id','min_stock'])]`,
  cast `min_stock => decimal:4`, `warehouse(): BelongsTo`, `stockable(): MorphTo`.
- **`Warehouse`** — add `reorderLevels(): HasMany`.
- **`Product`** / **`RawMaterial`** — remove `min_stock` from `$fillable`, the
  `casts()` entry, and the `@property` docblock.

## DTOs

- **New** `App\Data\WarehouseItemData` — one row of the detail table (represents a
  catalog item *in the context of a warehouse*, whether or not it has stock):
  ```php
  #[TypeScript]
  class WarehouseItemData extends Data
  {
      public function __construct(
          public string $stockable_type, // "product" | "raw_material" (alias — the PUT target)
          public int $stockable_id,
          public string $item,           // name
          public ?string $sku,
          public string $type,           // "Product" | "Raw material" (label)
          public string $unit,
          public float $on_hand,         // quantity in THIS warehouse (0 if none)
          public float $min_stock,       // THIS warehouse's threshold (0 if unset)
          public bool $needs_reorder,    // min_stock > 0 && on_hand < min_stock
      ) {}

      // Build from a raw UNION row (stdClass). The union has NO `type` label or
      // `needs_reorder` column (only the lowercase `stockable_type` alias), and
      // DB::table scalars come back as strings — so derive + cast here. A bare
      // `::from($row)` THROWS (CannotCreateData: missing type, needs_reorder).
      // Mirrors StockMovementData::fromStockMovement.
      public static function fromRow(object $row): self
      {
          $onHand = (float) $row->on_hand;
          $min = (float) $row->min_stock;

          return new self(
              stockable_type: $row->stockable_type,
              stockable_id: (int) $row->stockable_id,
              item: $row->item,
              sku: $row->sku,
              type: $row->stockable_type === 'product' ? 'Product' : 'Raw material',
              unit: $row->unit,
              on_hand: $onHand,
              min_stock: $min,
              needs_reorder: $min > 0 && $onHand < $min,
          );
      }
  }
  ```
  Frontend row id = `` `${stockable_type}:${stockable_id}` `` (there may be no
  single stock-row id, since unstocked items appear).
- **`ProductData`** / **`RawMaterialData`** — remove the `min_stock` field + its
  mapping.
- Regenerate: `bun run types:generate` (adds `App.Data.WarehouseItemData`, drops
  `min_stock` from the two item DTOs).

## Route & controllers

- **`routes/tenant.php`:**
  - Add `'show'` to the warehouses resource:
    `Route::resource('warehouses', WarehouseController::class)->only(['index','store','update','destroy','show'])`.
  - Add the reorder-level write:
    `Route::put('warehouses/{warehouse}/reorder-levels', [WarehouseReorderLevelController::class, 'update'])->name('warehouses.reorder-levels.update')`.
  - **After editing routes, regenerate Wayfinder helpers** so
    `warehousesRoutes.show.url(...)` and the reorder-levels helper exist —
    `php artisan wayfinder:generate` (or any `bun run dev` / `bun run build`, via
    `@laravel/vite-plugin-wayfinder`). This is **separate** from `bun run
    types:generate` (`typescript:transform`), which emits DTOs only; without the
    Wayfinder regen, `bun run types:check` fails on the missing route helpers.

- **`WarehouseController::show(Request $request, Warehouse $warehouse): Response`**
  - Eager-loads `location`.
  - `$view = (string) $request->string('view')` — `'all'` shows every catalog
    item; anything else (default) shows only in-stock/alerting items. **The cast is
    required:** `$request->string()` returns an `Illuminate\Support\Stringable`, and
    `$view !== 'all'` strict-compares an object → always true → the in-stock filter
    would wrongly apply to `?view=all` (matches the file's own `(string) …` idiom
    for `search`).
  - **Item list** (see *Building the item list* below) → paginated (`perPage`),
    `withQueryString()`, `->through(fn (object $row) => WarehouseItemData::fromRow($row))`
    (**not** `::from()` — see the DTO), ordered by `on_hand` **descending** with a
    deterministic tiebreaker.
  - **Summary counts** (full set, not the page). Both must **exclude soft-deleted
    items** so they reconcile with the trashed-excluded item list — products and
    raw materials `SoftDeletes`, and deleting an item does **not** clean up its
    `warehouse_stocks` / `warehouse_reorder_levels` rows (the new FK cascades only
    on *warehouse* delete). Filter via `whereHasMorph('stockable', [Product::class,
    RawMaterial::class])`, which applies each model's SoftDeletes scope:
    - `in_stock` = `WarehouseStock::where('warehouse_id',$id)->where('quantity','>',0)`
      `->whereHasMorph('stockable', [Product::class, RawMaterial::class])->count()`.
    - `needs_reorder` = reorder-levels for this warehouse with `min_stock > 0`,
      `whereHasMorph('stockable', […])`, left-joined to `warehouse_stocks` on the
      same `(warehouse, stockable)`, counting `COALESCE(quantity,0) < min_stock`.
    (Equivalently, derive both as `count(on_hand>0)` / `count(alerting)` off the same
    UNION subquery, which already excludes trashed items per leg — reconciles by
    construction.)
  - Renders `tenant/warehouses/show` with
    `['warehouse' => WarehouseData::from($warehouse), 'items' => $items,
    'summary' => ['in_stock' => …, 'needs_reorder' => …],
    'filters' => ['search' => $search, 'per_page' => $perPage, 'view' => $view]]`.

- **`WarehouseReorderLevelController::update(WarehouseReorderLevelRequest $request, Warehouse $warehouse): RedirectResponse`**
  - Validated: `stockable_type` `in:product,raw_material`; `stockable_id`
    `required|integer` and **exists in the matching table** (a `Rule::exists`
    chosen by `stockable_type`); `min_stock` `numeric|min:0`.
  - Upserts:
    `WarehouseReorderLevel::updateOrCreate(['warehouse_id'=>$warehouse->id,'stockable_type'=>…,'stockable_id'=>…], ['min_stock'=>…])`.
    (`min_stock = 0` keeps a row with 0 — harmless; 0 = no alert.)
  - `$this->toast('Reorder level updated.')`; `return back()` (Inertia partial
    reload from the client refreshes `items` + `summary`).

## Building the item list (the load-bearing query)

Both views resolve, **per warehouse**, each item's `on_hand` (from
`warehouse_stocks`) and `min_stock` (from `warehouse_reorder_levels`). Because
items span **two** morph tables (`products`, `raw_materials`), build a `UNION ALL`
of two per-type subqueries and paginate the wrapper:

```php
$whId = $warehouse->id;
$leg = fn (string $table, string $alias) => DB::table($table)
    ->selectRaw("
        '{$alias}' as stockable_type, {$table}.id as stockable_id,
        {$table}.name as item, {$table}.sku as sku, {$table}.unit as unit,
        COALESCE(ws.quantity, 0) as on_hand,
        COALESCE(rl.min_stock, 0) as min_stock
    ")
    ->leftJoin('warehouse_stocks as ws', fn ($j) => $j
        ->on('ws.stockable_id', '=', "{$table}.id")
        ->where('ws.stockable_type', $alias)->where('ws.warehouse_id', $whId))
    ->leftJoin('warehouse_reorder_levels as rl', fn ($j) => $j
        ->on('rl.stockable_id', '=', "{$table}.id")
        ->where('rl.stockable_type', $alias)->where('rl.warehouse_id', $whId));

$union = $leg('products', 'product')->unionAll($leg('raw_materials', 'raw_material'));

$items = DB::query()->fromSub($union, 'items')
    // Default view = in stock OR currently alerting, so an out-of-stock item that
    // has a reorder level (on_hand 0 < min) still shows (with its Reorder badge)
    // and the table can't contradict the "Needs reorder" tile. Grouped so the
    // search-OR below still ANDs onto the whole thing.
    ->when($view !== 'all', fn ($q) => $q->where(fn ($g) => $g
        ->where('on_hand', '>', 0)
        ->orWhere(fn ($r) => $r->where('min_stock', '>', 0)
            ->whereColumn('on_hand', '<', 'min_stock'))))
    ->when($search !== '', fn ($q) => $q->where(fn ($g) => $g
        ->where('item', 'like', $like)->orWhere('sku', 'like', $like)))
    ->orderByDesc('on_hand')      // primary: biggest holdings first
    ->orderBy('stockable_type')   // deterministic tiebreaker — (type, id) is
    ->orderBy('stockable_id')     // unique across the two UNION legs
    ->paginate($perPage)
    ->withQueryString();
// map each stdClass row → WarehouseItemData::fromRow (derives the type label +
// needs_reorder; casts the string DB scalars). NOT ::from() — it would throw.
```

- **Default (in-stock) view** = the same query filtered to `on_hand > 0` **OR
  alerting** (`min_stock > 0 && on_hand < min_stock`) — one code path, a flag flips
  the filter. This keeps zero-stock reorder alerts visible so the table and the
  "Needs reorder" tile always agree. (`summary.in_stock` stays a strict `quantity >
  0` count, so it may be smaller than the visible row count — intended.)
- **Search grouping matters:** the `item/sku` OR is wrapped in a
  `where(fn ($g) => …)` closure so it ANDs onto the `on_hand > 0` filter. A bare
  top-level `orWhere` would emit `(on_hand > 0) OR (name like …)` and leak
  zero-stock rows into the in-stock view (same OR-precedence footgun as
  `StockMovementController::applySearch`, which wraps its ORs for this reason).
- **Soft-deletes:** the per-type legs read `products` / `raw_materials` directly, so
  add `->whereNull("{$table}.deleted_at")` to each leg (both models use
  `SoftDeletes`) — trashed items never appear.
- **Sort must be total for stable pagination.** `on_hand` is non-unique (the whole
  All-items catalog ties at 0), so ordering on it alone lets LIMIT/OFFSET pages
  duplicate/drop rows on MySQL. The `(stockable_type, stockable_id)` tiebreaker is
  unique across the union → deterministic pages. `needs_reorder` is derived in the
  DTO, not sorted on.

## Frontend — `resources/js/pages/tenant/warehouses/show.tsx`

`TenantLayout` (app shell + breadcrumbs), **not** the print layout.

- **Breadcrumbs:** Dashboard › Warehouses › {name}.
- **Header:** `{name}` (h1); sub-line `{location} · {code} · {address}` (parts
  omitted when null). Right-aligned quick actions:
  - `Adjust stock` (primary) → `stock-movements?warehouse={id}`.
  - `Transfer` (outline) → `stock-transfers?from={id}`.
- **Summary tiles** — three branded shadcn `Card`s (indigo/neutral, light **and**
  dark, lucide icons):
  1. **In stock** — `{summary.in_stock}` items (`Package`).
  2. **Needs reorder** — `{summary.needs_reorder}` items; card turns **amber**
     (`text-amber-*`/`border-amber-*`, dark-aware) when `> 0`, neutral at 0
     (`TriangleAlert`). Subtitle: *"below this warehouse's reorder level"*.
  3. **Location** — `{warehouse.location}` (`MapPin`).
- **View toggle** — a segmented control (shadcn `Tabs` or a button group) above the
  table: **In stock** (default) · **All items**. Switches `?view=` while **preserving
  the active search/per-page**:
  `router.get(warehousesRoutes.show.url({ tenant: tenant.slug, warehouse: warehouse.id },
  { query: { view: next, search: filters.search || undefined, per_page: filters.per_page } }),
  {}, { only: ['items','filters'], preserveState: true, preserveScroll: true })`.
  Copy hint on All items: *"Set a reorder level even for items not yet stocked here."*
- **View-aware `baseUrl` (load-bearing):** the `DataTable`'s own search/per-page
  reloads send only `{ search, per_page }`, which Inertia **merges into `baseUrl`'s
  query string** — so `baseUrl` MUST carry the current view or the first keystroke
  drops `view=all` and the server reverts to in-stock (All-items collapses):
  ```tsx
  const base = warehousesRoutes.show.url(
    { tenant: tenant.slug, warehouse: warehouse.id },
    filters.view === 'all' ? { query: { view: 'all' } } : undefined,
  );
  ```
  Deriving `base` from the server-authoritative `filters.view` recomputes it when the
  toggle flips. (Wayfinder's `url(args, { query })` supports this; the paginator's own
  `withQueryString()` page links already keep `view`.)
- **Stock table** — shared `DataTable` (server search + pagination), `baseUrl={base}`.
  Columns:
  | Item | SKU | Type | On hand | Min here | Unit |
  - **Item**: name + inline amber **`Reorder`** `Badge` when `needs_reorder`
    (tooltip: *"On hand ({on_hand}) is below this warehouse's reorder level ({min_stock})"*).
  - **SKU**: `font-mono text-xs text-muted-foreground`, `—` when null.
  - **Type**: `Product` / `Raw material` (muted).
  - **On hand**: `formatQuantity(on_hand)` — this warehouse.
  - **Min here**: an inline number `Input` (small, right-aligned) — a **controlled
    component over React local state seeded from the row's `min_stock`** (not bound
    straight to the prop, so an uncommitted keystroke shows and a dropped refresh
    doesn't blank it). `aria-label={`Reorder level for ${item}`}` (the column header
    is not the input's accessible name). Commits on **Enter or blur** when changed →
    `router.put(reorderLevelsUrl, { stockable_type, stockable_id, min_stock },
    { preserveScroll: true, preserveState: true, only: ['items','summary'],
    onStart, onFinish, onSuccess: toast, onError: () => setLocal(String(row.min_stock)) })`.
    **Drive the disabled+spinner from `onStart`/`onFinish`, not `onSuccess`/`onError`:**
    `router.put` is single-flight, so a rapid second commit interrupts the first,
    firing only `onCancel`+`onFinish` — a spinner cleared only in `onSuccess`/`onError`
    would stick. No request per keystroke; `0`/empty clears the alert. (`onError`
    fires on a validation `back()` with errors, which does not replace the row, so
    `row.min_stock` is a valid rollback source.)
  - **Unit**: muted.
  - `searchPlaceholder="Search item or SKU…"`, `only={['items','filters']}`,
    `getRowId={(r) => `${r.stockable_type}:${r.stockable_id}`}`.
- **States** (all real, per UI-UX guidelines):
  - **Empty, default view** (no rows returned for the current view+search, i.e.
    `items.data.length === 0` with no search — **not** `in_stock === 0`, so a warehouse
    holding only out-of-stock *alerts* still shows those alert rows): `EmptyState` —
    `Package`, *"No stock in this warehouse yet"*, *"Receive or adjust stock to see it
    here — or switch to All items to set reorder levels."*, action `Adjust stock`
    (deep-link).
  - **Empty, all-items view** (no catalog at all): `EmptyState` pointing to
    Products / Raw materials.
  - **Search-empty / Loading**: the DataTable's existing affordances.

## List navigation & accessibility — `warehouses/index.tsx` + `data-table.tsx`

- **`DataTable` gains an optional `rowHref?: (row: TData) => string`.** When
  provided, `<TableRow>` gets `cursor-pointer` + hover and an `onClick` that
  **bails in three cases** before navigating:
  ```tsx
  onClick={(event) => {
    if (window.getSelection()?.toString()) return;                 // click-drag to copy, don't navigate
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return; // new-tab → the <Link>
    if ((event.target as HTMLElement).closest('a,button,input,[role="menuitem"],[data-slot="dropdown-menu-trigger"]')) return; // ⋯ menu, inline inputs, links
    router.visit(rowHref(row));
  }}
  ```
  (Note the added `input` in the closest() list — the detail table's inline
  min-stock inputs must never trigger a row navigation.) Absent `rowHref` → every
  other table renders exactly as today.
- **Keyboard / new-tab:** the warehouse **name cell becomes a real Inertia `<Link>`**
  to `warehouses.show` (focusable, Cmd/Ctrl-click = new tab); the row `onClick` is a
  mouse convenience on top.
- `warehouses/index.tsx` passes
  `rowHref={(w) => warehousesRoutes.show.url({ tenant: tenant.slug, warehouse: w.id })}`
  and wraps the name cell in that `<Link>`.

## Quick-action deep-links — `stock-movements/index.tsx` + `stock-transfers/index.tsx`

Both pages drive their create form via `useResourceDialog` + a `warehouseId`
(resp. `fromWarehouseId`) `useState`. Add a mount effect that reads the query param
once and pre-scopes the form:

- **stock-movements**: read `warehouse`; if it matches a known option id, call
  `dialog.openCreate()` **first**, then `setWarehouseId(id)`. **Order is
  load-bearing:** `openCreate()` synchronously runs the `useResourceDialog`
  `onCreate` reset (which clears `warehouseId` to `''`), so the pre-fill must be the
  *last* write or the combobox opens empty. (Verified: `use-resource-dialog.ts`
  calls `onCreate?.()` before `setOpen(true)`.)
- **stock-transfers**: `from` → `dialog.openCreate()` then `setFromWarehouseId(id)`.
- **One-shot consume:** after opening, strip the param so a reload / Back-Forward
  remount doesn't force a dismissed dialog open —
  `const url = new URL(window.location.href); url.searchParams.delete('warehouse');
  window.history.replaceState(window.history.state, '', url);` (pass existing
  `window.history.state` to keep Inertia's cached page object; **not** `router.replace`
  — extra visit — nor a bare `replaceState(null,…)` — drops Inertia state).
- Unknown/absent id → no-op.

## Dashboard redefinition — `DashboardController` + `dashboard.tsx`

`min_stock` no longer exists on items, so `lowStock()` is rebuilt around
`warehouse_reorder_levels`:

- **Reorder alerts are per `(warehouse, item)`:** for every reorder-level with
  `min_stock > 0`, resolve that warehouse's on-hand for the item
  (`COALESCE(warehouse_stocks.quantity, 0)`, joined on the same warehouse+item); an
  alert is any row where `on_hand < min_stock`. Each alert row:
  `{ warehouse_id, warehouse (label "Location · Warehouse"), type, id, name, sku,
  on_hand, min_stock, deficit }` (`deficit = min_stock - on_hand`), item name/sku
  resolved per morph type.
- **Exclude trashed warehouses AND trashed items.** Soft-deleting either does not
  drop `warehouse_reorder_levels` (the FK cascades only on a *hard* warehouse
  delete), so an orphaned level can outlive its warehouse or item. Join
  `warehouses` (also the source of the "Location · Warehouse" label) with
  `whereNull('warehouses.deleted_at')`, and exclude trashed items with
  `whereNull('products.deleted_at')` / `whereNull('raw_materials.deleted_at')` in
  each per-morph name-resolution join. **This preserves today's behaviour:** the
  current `lowStock()` resolves items via Eloquent (`Product::where(...)->get()`), so
  its SoftDeletes scope already hides trashed items — the rewrite must not regress it.
- **KPIs:** `low_stock.count` = number of alert rows; `low_stock.out_of_stock` =
  alerts with `on_hand <= 0`. (`skus_in_stock`, `onHandByWarehouse`, and the charts
  are unaffected — they never used `min_stock`.)
- **`reorderList`** = alerts sorted by `deficit` desc, `take(8)`, now carrying the
  **warehouse** (keep `warehouse_id` in the payload — needed for the React key below).
- **`dashboard.tsx`**:
  - `ReorderRow` gains `warehouse: string` (the label, for a new column/subtitle) **and
    `warehouse_id: number`**.
  - **Fix the React key:** the card currently keys rows `` `${row.type}:${row.id}` `` —
    now that one item can alert in multiple warehouses, that collides. Change to
    `` `${row.warehouse_id}:${row.type}:${row.id}` ``.
  - **Relabel the StatCard.** `low_stock.count` now counts per-`(warehouse, item)`
    alerts (one item low in N warehouses = N), so the "Low-stock items" / "below
    minimum" copy over-counts *items*. Rename to **"Reorder alerts"** with sub copy
    **"below reorder level"** (and the `out_of_stock` sub as out-of-stock *alerts*) —
    consistent with the detail page's "Needs reorder" tile.

## Removing item-level `min_stock` — remaining touch-points

- **`ProductRequest` / `RawMaterialRequest`** — drop the `min_stock` rule and the
  `defaultBlankToZero('min_stock')` call.
- **`products/index.tsx` / `raw-materials/index.tsx`** — remove the `min_stock`
  form field (state, input, error) and the `min_stock` table column.
- **Cross-cutting test fixtures** — six feature tests seed a now-dead
  `'min_stock' => …` key in `Product::create` / `RawMaterial::create`:
  `ProductionOrderTest` (L30-32, 139), `SalesOrderTest` (L33), `StockTransferTest`
  (L150), `StockMovementTest` (L180), `PurchaseOrderTest` (L31-32), `BomTest`
  (L22-24). Strict model mode is **off**, so the key is silently discarded and the
  tests still pass — but strip it from each so the grep below is truly clean.
- Verify no other reader remains (grep `min_stock` → only the new
  `warehouse_reorder_levels` world).

## Tests (TDD — author before implementing)

Pest feature tests init tenancy and build `Location → Warehouse → (items, stock,
reorder levels)` by direct model creation.

- **`WarehouseReorderLevel`** — fillable/casts; `unique(warehouse, stockable)`
  enforced; `min_stock` defaults to `0`.
- **`WarehouseReorderLevelController::update`** — creates a level; updating the same
  `(warehouse, item)` upserts (no duplicate row); `min_stock = 0` is accepted;
  rejects `min_stock < 0`, an unknown `stockable_type`, and a `stockable_id` absent
  from the matching table; is tenant-scoped (auth required); flashes the toast.
- **`WarehouseController::show`**
  - default view: `items` contains this warehouse's `on_hand > 0` rows **plus**
    out-of-stock *alerting* rows (`min_stock > 0 && on_hand < min_stock`); another
    warehouse's stock and a genuinely idle zero-stock item (no level) are excluded.
  - **out-of-stock alert visible + reconciles:** an item with `min_stock > 0` and
    `on_hand = 0` appears in the **default** view (badge shown) and the count of such
    rows equals `summary.needs_reorder` (tile ↔ table never contradict).
  - **stable pagination:** seed more items than one page all sharing an `on_hand`
    value (and, for `?view=all`, more than a page of `on_hand = 0` rows); page through
    at a small `perPage` and assert the concatenated ids are unique with none missing
    (the `(stockable_type, stockable_id)` tiebreaker holds).
  - `?view=all`: includes **unstocked** catalog items (products **and** raw
    materials) with `on_hand = 0`, and their `min_stock` (0 when unset, the set
    value when a level exists); trashed items excluded.
  - `needs_reorder` per row: `min_stock > 0 && on_hand < min_stock`, boundary
    `on_hand === min_stock` ⇒ false.
  - **soft-deleted item excluded everywhere:** a stocked item with a reorder level,
    then soft-deleted, disappears from `items` **and** drops both `summary.in_stock`
    and `summary.needs_reorder` by one (no phantom inflation).
  - `?search=` narrows to matching item/sku **and stays warehouse/in-stock scoped
    while searching** — seed a matching item held in a *second* warehouse and a
    *zero-stock non-alerting* matching item; assert both are absent from the
    default-view search (guards the OR-precedence leak).
  - a warehouse with no stock and no levels → empty default-view `items`,
    `summary.in_stock === 0`.
- **`WarehouseItemData::fromRow`** — maps product and raw-material union rows: `type`
  label derived from the alias, null sku, `on_hand`/`min_stock` cast to floats,
  `stockable_id` to int, `needs_reorder` at the boundary.
- **Dashboard** — rewrite `DashboardTest` for per-warehouse alerts: an item with a
  reorder level in warehouse A and on-hand below it **is** listed (with A as its
  warehouse); the same item well-stocked in warehouse B is **not** a separate alert;
  an item with `min_stock = 0` never alerts; `out_of_stock` counts zero-on-hand
  alerts; `reorderList` is deficit-sorted and includes the warehouse. **Adapt the
  existing "stranded in a soft-deleted warehouse" regression** (drop its removed
  `min_stock => 5` field): seed on-hand 10 + a level of 5 for `(warehouse, item)`,
  soft-delete the warehouse, and assert on-hand surfaces still drop it
  (`skus_in_stock.count === 0`) **and** the orphaned level raises **no** alert
  (`low_stock.count === 0`, `reorderList` empty) — proving the `whereNull(
  warehouses.deleted_at)` filter. Add a **trashed-item** case: soft-delete an item
  that has a level below its on-hand and assert it does not alert.
- **Product / RawMaterial** — drop the `min_stock` assertions/fields; remove the
  "rejects non-integer min_stock" / "defaults min_stock to 0" cases (field is gone).
- **Route** — `warehouses.show` and `warehouses.reorder-levels.update` registered
  and tenant-scoped.

Frontend has no unit tests here (Pest only); verify by running the app (login →
demo tenant → open a warehouse) in the finalize step, specifically:
- toggle **All items**, then **type in search / change per-page** → unstocked rows
  stay (view=all survives the DataTable's own reloads — the view-aware `baseUrl`);
- edit a **Min here**, press Enter → toast, and the row badge + "Needs reorder" tile
  + dashboard "Reorder alerts" all update;
- a rapid second edit doesn't leave the first row's spinner stuck.

## Edge cases & risks

- **Union query correctness** — the whole page hinges on the two-leg `UNION ALL`
  with per-warehouse left joins. Load-bearing details, each with a test: the
  `LEFT JOIN` predicates (`warehouse_id`, `stockable_type`) live in the **join `ON`**
  (a where on the outer query would drop unstocked rows); `?view` is cast to a plain
  **string** before `!== 'all'`; the default filter keeps `on_hand > 0` **OR
  alerting** rows; the search OR is wrapped so it ANDs onto that filter; soft-deleted
  items are excluded per leg; and the order carries a `(stockable_type, stockable_id)`
  **tiebreaker** so `LIMIT/OFFSET` pages don't duplicate/drop tied `on_hand` rows.
- **Soft-delete consistency** — items soft-delete but keep their stock/level rows
  (FK cascades only on warehouse hard-delete). Every full-set surface — the item
  list, `summary.in_stock`/`needs_reorder`, and the dashboard alerts — filters
  trashed items so the tiles, the table, and the dashboard reconcile (and today's
  Eloquent-scoped dashboard behaviour isn't regressed).
- **Reorder flag = per-warehouse, dashboard-consistent** — the badge means "below
  *this warehouse's* level"; the dashboard lists the same per-`(warehouse, item)`
  alerts, so numbers reconcile. No global default remains to disagree with.
- **Inline edit** — controlled input over **local state** (seeded from `min_stock`),
  `aria-label`ed; commits on Enter/blur (not per keystroke); the input is in the
  row-click `closest()` bail list so editing never navigates; spinner driven by
  `onStart`/`onFinish` (Inertia `put` is single-flight — a rapid second commit
  interrupts the first, firing only `onCancel`/`onFinish`); failed PUT rolls back +
  toasts; success partial-reloads `items` + `summary` so badge/tile update.
- **Row-click guards** — bail on text selection, modified/non-primary clicks, and
  interactive descendants (`⋯` menu, inline inputs, links).
- **Deep-link one-shot** — `?warehouse`/`?from` stripped after opening so reload /
  Back-Forward doesn't reopen a dismissed dialog.
- **Pagination vs. summary** — `in_stock` / `needs_reorder` are dedicated full-set
  queries, never derived from the current page.
- **Migration/reseed** — dropping item `min_stock` + adding the new table is a
  pre-launch in-place edit; `migrate:fresh` + reseed. (The sole seeder,
  `DatabaseSeeder`, seeds only a central super-admin — no inventory / `min_stock` —
  so nothing there needs changing; note this only in case an inventory seeder is
  added later, which should seed `warehouse_reorder_levels`.)
- **Vendored UI untouched** — `rowHref` and the inline input live in app components
  (`components/data-table.tsx`, the page), never `components/ui/**`.

## Out of scope (explicitly not now)

- No bulk threshold editing / import (one inline edit at a time).
- No per-warehouse threshold on the item (product/material) page — set on the
  warehouse page only, per decision.
- No stock **valuation** / cost columns.
- No per-warehouse movement history tab (global ledger already exists).
- No auto-reorder / PO suggestions from the alerts (future).
- No bin/shelf sub-locations (dropped earlier).
