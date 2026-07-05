# Catalog Phase 2 — Suppliers, Customers, Raw materials — design

**Date:** 2026-07-05
**Status:** Approved (pending spec review)
**Area:** Tenant catalog (`/{slug}/…`, `web` guard, tenant DB)
**Master spec:** `docs/superpowers/specs/2026-07-04-next-to-laravel-inertia-conversion-design.md` (§5, §10 Phase 2)

## Goal

Continue Phase 2 (Catalog). Categories is already built; this adds the next three
catalog entities — **Suppliers**, **Customers**, **Raw materials** — each a
per-tenant CRUD module. **Products** is deferred to its own cycle (it adds image
upload + category/supplier pickers and depends on suppliers existing first).

## Approach

Three **independent** CRUD modules, each cloning the proven **Categories**
reference pattern (`app/Http/Controllers/Tenant/CategoryController.php`,
`app/Http/Requests/Tenant/CategoryRequest.php`, `app/Models/Category.php`,
`resources/js/pages/tenant/categories/index.tsx`, `tests/Feature/Tenant/CategoryTest.php`):

- One Eloquent model (`Fillable` attribute + `SoftDeletes`).
- One per-tenant migration (`database/migrations/tenant/`).
- One FormRequest (authorize = authenticated; rules with unique-ignore on update).
- One resource controller — `index` (paginate + search), `store`, `update`,
  `destroy` (soft delete); mutations `back()->with('success', …)`.
- One route line: `Route::resource(...)->only(['index','store','update','destroy'])`
  inside the tenant `auth:web` group.
- One Inertia page cloned from `categories/index.tsx` with the entity's fields.
- One sidebar nav item.
- One Pest test file mirroring `CategoryTest`.

**Rejected:** a shared generic CRUD base/abstraction — the entities differ in
fields/validation, the codebase already uses one-file-per-entity (Categories), and
abstracting three similar-but-not-identical modules now is premature (YAGNI).

All three are tenant-scoped: they live in each tenant's database, authenticate via
the `web` guard, and route under `/{tenant}/…`. No `organization_id` columns (the
DB is the tenant).

## Data model

Cross-cutting per master-spec conventions: `bigint` PK, `SoftDeletes`, timestamps,
`decimal(12,4)` for quantities.

### `suppliers` and `customers` (identical shape)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string(255) | required |
| `email` | string(255) nullable | valid email, **unique** (per tenant; multiple NULLs allowed) |
| `phone` | string(50) nullable | free text |
| `address` | text nullable | |
| `notes` | text nullable | ≤1000 chars |
| timestamps, `deleted_at` | | |

### `raw_materials`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string(255) | required |
| `sku` | string(100) | required, **unique** (per tenant) |
| `unit` | string(20) | required, free text ("kg", "pcs", "L") |
| `min_stock` | decimal(12,4) | default 0, ≥0 |
| timestamps, `deleted_at` | | |

Migrations (tenant, sequential after the existing `…000002`):
`2026_07_05_000003_create_suppliers_table.php`,
`…000004_create_customers_table.php`, `…000005_create_raw_materials_table.php`.

## Validation (FormRequests)

`authorize()` returns `$this->user() !== null` (route already gated by `auth:web`).

**Supplier / Customer** rules:
- `name` — `required`, `string`, `max:255`
- `email` — `nullable`, `string`, `email`, `max:255`, `Rule::unique('{table}','email')->ignore($id)`
- `phone` — `nullable`, `string`, `max:50`
- `address` — `nullable`, `string`, `max:1000`
- `notes` — `nullable`, `string`, `max:1000`

`email` is nullable + unique: the `nullable` rule short-circuits when empty so the
unique check only runs for a provided email, and the DB unique index permits
multiple NULL emails. Name is not unique (duplicate names allowed).

**Raw material** rules:
- `name` — `required`, `string`, `max:255`
- `sku` — `required`, `string`, `max:100`, `Rule::unique('raw_materials','sku')->ignore($id)`
- `unit` — `required`, `string`, `max:20`
- `min_stock` — `nullable`, `numeric`, `min:0` (defaults to 0 when omitted)

`$id` comes from the route-bound model on update (`$this->route('supplier')`, etc.),
matching how `CategoryRequest` reads `$this->route('category')`.

## Controllers

Each mirrors `CategoryController`:
- `index(Request)` — `per_page` clamp `[10,25,50,100]` (default 10); trimmed `search`;
  `when($search !== '')` LIKE group over the entity's searchable columns
  (**suppliers/customers: name + email; raw materials: name + sku**); `latest()`;
  `->paginate()->withQueryString()->through()` mapping to the fields the page needs
  (`id`, the entity fields, `created_at`); renders `tenant/{plural}/index` with
  `{entity}s` + `filters` props.
- `store(FormRequest)` — `Model::create($request->validated())`;
  `back()->with('success', '{Entity} created.')`.
- `update(FormRequest, Model)` — `$model->update($request->validated())`;
  `back()->with('success', '{Entity} updated.')`.
- `destroy(Model)` — `$model->delete()`; `back()->with('success', '{Entity} deleted.')`.

Route-model binding uses the default `id` key. Prop key names: `suppliers`,
`customers`, `rawMaterials` (page reads `rawMaterials`).

## Routes (`routes/tenant.php`, inside `auth:web`)

```php
Route::resource('suppliers', SupplierController::class)->only(['index','store','update','destroy']);
Route::resource('customers', CustomerController::class)->only(['index','store','update','destroy']);
Route::resource('raw-materials', RawMaterialController::class)
    ->parameters(['raw-materials' => 'rawMaterial'])
    ->only(['index','store','update','destroy']);
```

`raw-materials` uses a kebab URI; `->parameters(['raw-materials' => 'rawMaterial'])`
renames the default `{raw_material}` route parameter to `{rawMaterial}` so implicit
binding matches a `RawMaterial $rawMaterial` controller argument (and the FormRequest
reads `$this->route('rawMaterial')`). Names: `tenant.suppliers.*`,
`tenant.customers.*`, `tenant.raw-materials.*`.

## Frontend

Each page (`resources/js/pages/tenant/{suppliers,customers,raw-materials}/index.tsx`)
is `categories/index.tsx` cloned with the entity's field set:
- `TenantLayout` with breadcrumbs (Dashboard → {Entity}).
- Branded card + table, columns per entity (suppliers/customers: Name · Email · Phone;
  raw materials: Name · SKU · Unit · Min stock), row actions dropdown (Edit / Delete).
- Debounced (350 ms) server search, per-page `Select`, pagination footer, always-mounted
  `sr-only role="status"` region, `aria-busy` container.
- Create/Edit `Dialog` — Inertia `<Form>` POST `${base}` or PUT `${base}/${id}`,
  `key={editing?.id ?? 'new'}`, controlled inputs, `InputError` under each field.
- Delete confirmation `Dialog` → `router.delete` with `preserveScroll` + flash toast.
- Empty state (no rows, no search) distinct from no-results (search miss).
- Follows `docs/UI-UX-GUIDELINES.md` (shadcn, indigo accent, all states handled, light+dark).

Sidebar (`resources/js/components/tenant/tenant-sidebar.tsx`): add **Suppliers**
(`Truck`), **Customers** (`Contact`), **Raw materials** (`Boxes`) nav items after
Categories, each linking to `/${slug}/{plural}`.

## Testing (Pest, TDD per module — `tests/Feature/Tenant/`)

Mirror `CategoryTest` (uses `ProvisionTenant` in `beforeEach` + a `loginAs…` helper):
1. Guest is redirected from the index to the tenant login.
2. Lists the tenant's rows, paginated (asserts `component`, `data` count, `total`,
   `filters.per_page`).
3. Creates a row (redirect back + `success` flash; row exists in the tenant DB).
4. Rejects a duplicate **email** (suppliers/customers) / duplicate **sku**
   (raw materials) — `assertSessionHasErrors`. (For suppliers/customers, also
   confirm two rows with *no* email are allowed — the nullable-unique case.)
5. Updates a row.
6. Soft-deletes a row (`find` null, `withTrashed()->find` not null).

Frontend gates after each module: `bun run check:ci` (0 errors), `bun run types:check`,
`bun run build`. PHP: `vendor/bin/pint --dirty`.

## Out of scope (this cycle)

- **Products** and **raw material ↔ product** links (BOM) — separate cycles.
- Low-stock badges / inventory UI for `min_stock` (Inventory/Dashboard phase).
- Supplier→purchase-history and customer→sales-history tabs (need Orders phase).
- A fixed unit dropdown (kept free-text for now).
- Import/export, bulk actions.
