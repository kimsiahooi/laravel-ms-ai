# Phase 3a — Warehouses + Locations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development.
> Steps use TDD (failing test first). This is the first slice of Phase 3 (Inventory core)
> from the master spec (`…/specs/2026-07-04-…-design.md` §5, §10.3). Slices 3b (stock
> ledger + movements) and 3c (transfers) follow separately.

**Goal:** CRUD for `warehouses` and `locations` — the containers that inventory stock will
later live in. Locations belong to a warehouse; `code` is unique per warehouse.

**Architecture:** identical to the existing catalog resources (Supplier ≈ Warehouse;
Product's FK picker ≈ Location's warehouse picker). Reuses every abstraction: `ResolvesPerPage`,
`Searchable`, `TenantFormRequest`, `RespondsWithToast`, laravel-data DTO (`#[TypeScript]`),
`DataTable` + `ResourceFormDialog` + `useResourceDialog` + `useDelete` + `ConfirmDeleteDialog`
+ `EmptyState` + `RowActions` + `usePageProps` + `ComboboxField`, Wayfinder route helpers, and the
`@/config/resources` descriptor. Tests use `Model::create()` (no factories — matches catalog).

## Schema (tenant migrations)

**`warehouses`** (template: suppliers migration)
- `id`, `name` (string), `code` (string 50, nullable, **unique**), `address` (text, nullable),
  `timestamps`, `softDeletes`.

**`locations`**
- `id`, `warehouse_id` (foreignId → warehouses, `cascadeOnDelete`), `code` (string 50),
  `name` (string, nullable), `timestamps`, `softDeletes`.
- `unique(['warehouse_id', 'code'])`.

## Fields / validation
- **Warehouse:** name required; code nullable + unique (ignore self on update); address nullable.
- **Location:** warehouse_id required + `exists:warehouses,id`; code required; name nullable;
  `code` unique per warehouse (`Rule::unique('locations')->where('warehouse_id', …)->ignore(self)`).

## Files (clone the named template, adapt fields)

**Warehouse** (template = Supplier)
- `database/migrations/tenant/2026_07_10_000001_create_warehouses_table.php`
- `app/Models/Warehouse.php` — `@property` docblock, `Fillable(['name','code','address'])`,
  `Searchable(['name','code'])`, `SoftDeletes`, `HasFactory`, `hasMany(Location::class)`.
- `app/Data/WarehouseData.php` — `#[TypeScript]`; id, name, code, address, created_at; `fromWarehouse`.
- `app/Http/Requests/Tenant/WarehouseRequest.php` — `extends TenantFormRequest`.
- `app/Http/Controllers/Tenant/WarehouseController.php` — `ResolvesPerPage` + `RespondsWithToast`;
  index `->search(...)->through(WarehouseData::from)`; store/update/destroy `$this->toast(...)`.
- `routes/tenant.php` — `Route::resource('warehouses', …)->only(['index','store','update','destroy'])`.
- `tests/Feature/Tenant/WarehouseTest.php` — create/update/delete (assertToast), validation,
  duplicate-code rejection, search.
- `resources/js/pages/tenant/warehouses/index.tsx` — clone suppliers page; fields name/code/address.

**Location** (template = Product minus image/min_stock; keep the FK picker)
- `database/migrations/tenant/2026_07_10_000002_create_locations_table.php`
- `app/Models/Location.php` — props; `Fillable(['warehouse_id','code','name'])`;
  `Searchable(['code','name'])`; `SoftDeletes`; `belongsTo(Warehouse::class)`.
- `app/Data/LocationData.php` — id, warehouse_id, warehouse (name via relation), code, name,
  created_at; `fromLocation`.
- `app/Http/Requests/Tenant/LocationRequest.php` — `extends TenantFormRequest`.
- `app/Http/Controllers/Tenant/LocationController.php` — like ProductController: index passes
  `'warehouses' => OptionData::collect(Warehouse::orderBy('name')->get(['id','name']))` for the picker.
- `routes/tenant.php` — `Route::resource('locations', …)->only([...])`.
- `tests/Feature/Tenant/LocationTest.php` — CRUD (assertToast), validation, unique-per-warehouse.
- `resources/js/pages/tenant/locations/index.tsx` — clone products page structure; warehouse
  `ComboboxField`; fields code/name; column shows warehouse name.

**Shared**
- `resources/js/config/resources.ts` — add `warehouseMeta` (icon `Warehouse`) + `locationMeta`
  (icon `MapPin`).
- `resources/js/components/tenant/tenant-sidebar.tsx` — add Warehouses + Locations nav items; while
  there, convert the whole `mainNavItems` array to Wayfinder helpers (it currently hard-codes
  `/${slug}/…` — the de-hardcode pass missed this component).
- Regenerate Wayfinder (`bun run build`) so `@/routes/tenant/warehouses|locations` exist.

## Verification
- `php artisan test --filter='Warehouse|Location' --compact` green (RefreshDatabase migrates the
  test DB).
- `php artisan tenants:migrate` on local dev DBs so the `demo` tenant has the tables.
- `bun run check:ci`, `bun run types:check`, `bun run build`, full `php artisan test --compact`.
- Browser smoke: create a warehouse, then a location under it (picker works), edit, delete.
