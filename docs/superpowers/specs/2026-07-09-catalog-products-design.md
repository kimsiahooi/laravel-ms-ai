# Catalog — Products (Phase 2, final module)

**Date:** 2026-07-09
**Status:** Approved (design)
**Depends on:** categories + suppliers modules (already shipped), server-side DataTable,
per-tenant filesystem tenancy (`FilesystemTenancyBootstrapper`, already enabled).

## 1. Purpose

Products is the last Phase 2 catalog module and the **anchor entity** the later phases
reference (sales order items, BOM items, production orders). It is the first module to
introduce **image upload** (native Laravel Storage, per-tenant) and **foreign-key pickers**
(category + supplier). It follows the established catalog pattern: a server-side
`<DataTable>` list plus a create/edit **dialog**, matching suppliers / customers /
raw-materials.

## 2. Scope

**In scope**

- `products` per-tenant table + `Product` model (soft-deletable).
- Full CRUD: list (search + pagination), create, edit, soft-delete — all via the shared
  `<DataTable>` and a create/edit dialog.
- Single **image** per product: upload, live preview, replace, remove. Stored on the
  per-tenant `public` disk, served via `tenant_asset()`.
- **Category** and **supplier** pickers as searchable **comboboxes** (nullable — "None"
  allowed).

**Out of scope (deferred)**

- Product **detail page + BOM tab** → Phase 5 (BOM does not exist yet).
- **Selling price** on the product → not added; unit prices are entered per line at order
  time (per master spec).
- Restore / force-delete UI, barcode uniqueness, bulk actions.

## 3. Decisions

| Decision | Choice | Rationale |
| --- | --- | --- |
| Form shape | **Dialog** (not dedicated pages / detail page) | Consistency with the other 4 catalog modules; fastest path. |
| Category/supplier picker | **Searchable combobox** (shadcn Popover + Command) | Scales as catalogs grow; nullable with a "None" option. |
| Price field | **None** | Master spec keeps products catalog-only; prices set on order lines. |
| `barcode` | nullable, **not unique** | Master spec annotates only `sku` as unique. |
| `min_stock` | **integer** | Products count in whole units (raw materials use `decimal`). |
| Image serving | `tenant_asset()` over the `public` disk | Reuses the already-working tenant-asset route (same one serving Vite build assets); no `storage:link` needed. |

## 4. Data layer

### Migration — `database/migrations/tenant/…_create_products_table.php`

Runs after `categories` and `suppliers` (earlier timestamps), so the FKs resolve.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigIncrements | |
| `name` | string | required |
| `sku` | string, **unique** | required |
| `barcode` | string, nullable | not unique |
| `description` | text, nullable | |
| `category_id` | foreignId nullable → `categories`, **nullOnDelete** | |
| `supplier_id` | foreignId nullable → `suppliers`, **nullOnDelete** | |
| `min_stock` | unsignedInteger, default `0` | |
| `unit` | string | required |
| `image` | string, nullable | storage path, e.g. `products/abc.webp` |
| `timestamps`, `softDeletes` | | |

> **FK vs soft delete:** categories/suppliers are soft-deleted, so `nullOnDelete` (a
> DB-level FK constraint) only fires on an actual force-delete, not a soft delete. That is
> acceptable — the pickers only list non-trashed options, and the UI falls back to "—" for a
> missing/trashed reference.

### Model — `app/Models/Product.php`

- `#[Fillable([...])]` — name, sku, barcode, description, category_id, supplier_id,
  min_stock, unit, image.
- `use SoftDeletes;`
- `casts(): ['min_stock' => 'integer']`.
- Relationships: `category(): BelongsTo`, `supplier(): BelongsTo`.
- Appended accessor `image_url`: `image ? tenant_asset($image) : null` (valid because every
  product route runs in a tenant context).

## 5. Validation — `app/Http/Requests/Tenant/ProductRequest.php`

- `name` → `required|string|max:255`
- `sku` → `required|string|max:255` + `Rule::unique('products','sku')->ignore($ignoreId)`
- `barcode` → `nullable|string|max:255`
- `description` → `nullable|string|max:2000`
- `category_id` → `nullable|` + `Rule::exists('categories','id')->whereNull('deleted_at')`
- `supplier_id` → `nullable|` + `Rule::exists('suppliers','id')->whereNull('deleted_at')`
- `min_stock` → `required|integer|min:0`; `prepareForValidation()` coerces blank → `0`
- `unit` → `required|string|max:50`
- `image` → `nullable|image|mimes:jpg,jpeg,png,webp|max:2048` (2 MB)
- `remove_image` → `nullable|boolean` (clear the image without uploading a new one)

## 6. Controller + routes

Route (tenant group, alongside the other catalog resources):

```php
Route::resource('products', ProductController::class)
    ->only(['index', 'store', 'update', 'destroy']);
```

`app/Http/Controllers/Tenant/ProductController.php`:

- **index(Request)** — paginate + search over `name` / `sku` / `barcode`, `per_page` filter,
  eager-load `category` + `supplier`, `->through()` mapping display fields (`id`, `name`,
  `sku`, `barcode`, `image_url`, `category` name, `supplier` name, `min_stock`, `unit`,
  `created_at`). Also pass `categories` and `suppliers` (each `id` + `name`, non-trashed,
  ordered by name) for the comboboxes. Render `tenant/products/index`.
- **store(ProductRequest)** — handle image (§7), create, `back()->with('success', 'Product created.')`.
- **update(ProductRequest, Product)** — handle image replace/remove (§7), update,
  `back()->with('success', 'Product updated.')`.
- **destroy(Product)** — soft delete (image file kept), `back()->with('success', 'Product deleted.')`.

## 7. Image handling

- **Store:** `$path = $request->file('image')->store(tenant('id'), 'assets')` on the dedicated
  **`assets`** disk (`config/filesystems.php`, root `storage/assets`). Files land at
  `storage/assets/{slug}/{hash}.ext` (the DB `image` column holds `{slug}/{hash}.ext`). The
  `assets` disk is **private + central** — deliberately **not** in `tenancy.filesystem.disks`,
  so it is not per-tenant suffixed; isolation is by the `{slug}` path segment plus the serve
  guard. It is never `storage:link`-ed, so images can't be exposed publicly. `storage/assets`
  is git-ignored (tracked `.gitignore`, contents ignored).
- **Serve:** a dedicated tenant-prefixed route `GET {tenant}/storage/{path}` (name
  `tenant.storage`, controller `TenantStorageController`, under `auth:web`) streams the file
  from `Storage::disk('assets')`. Guards: reject `..`; **require the path to start with the
  active tenant's slug** (`str_starts_with($path, tenant('id').'/')`) so one tenant can't read
  another's files on the shared disk; then 404 if missing. The `image_url` accessor builds
  `route('tenant.storage', ['tenant' => tenant('id'), 'path' => $image])`. **No `storage:link`**
  is used — images are private (served through PHP behind auth), and the route is reusable by
  future private uploads (avatars, logos).

  > ⚠️ Correction (as-built): the original design assumed `tenant_asset()` / the tenancy
  > package's asset route. That route is guarded by `InitializeTenancyByDomain`, but this app
  > is **path/slug**-identified with no domains, so it 500s (`Tenant::domains()` undefined).
  > Vite assets dodge it because `ViteBundler` uses `global_asset()`. A tenant-prefixed route
  > (so `InitializeTenancyByPath` runs) is the correct mechanism here.
- **Replace:** on update with a new file, delete the previous file
  (`Storage::disk('assets')->delete($old)`) then store the new one.
- **Remove:** when `remove_image` is truthy and no new file is uploaded, delete the file and
  set `image = null`.
- **Soft delete:** file is retained (record is restorable).

## 8. Frontend — `resources/js/pages/tenant/products/index.tsx`

- **`<DataTable>`** columns: **thumbnail** (rounded `img`, placeholder icon when null) ·
  **name** · **sku** (mono) · **category** (or "—") · **supplier** (or "—") · **min stock**
  (right-aligned, tabular) · **unit** (hidden below `md`) · **actions** (edit / delete
  dropdown). Search placeholder "Search name, SKU or barcode…".
- **Create/edit dialog** (same structure as `raw-materials/index.tsx`) with fields: name,
  sku, barcode, unit, **category combobox**, **supplier combobox**, min_stock (number),
  **image** (file input + live preview via `URL.createObjectURL`, existing `image_url` on
  edit, and a "remove" control that sets `remove_image`), description (**textarea**).
  Inertia `<Form>` auto-sends multipart for the file and spoofs `_method=put` on edit.
- **Delete confirmation dialog** — same pattern as the other modules ("can be restored later").
- **New shadcn primitives** to add if missing: `combobox` (composed from `command` +
  `popover`) and `textarea`.

## 9. Testing (TDD, Pest) — `tests/Feature/Tenant/ProductTest.php`

Uses `Storage::fake('public')`. Covers:

- index renders `tenant/products/index` and passes `products`, `filters`, `categories`,
  `suppliers`.
- search matches name / sku / barcode; pagination + `per_page` respected.
- store creates a product with and without an image; asserts the `image` path is persisted
  and the file exists on the tenant's `public` disk (checked **inside `$tenant->run()`** —
  `Storage::fake` is defeated by the tenancy filesystem bootstrapper, so tests use the real
  tenant-suffixed disk; `TestCase` cleans those dirs, hard-guarded to the `msai_test_tenant_`
  suffix). Also covered: the served image returns HTTP 200 (`GET {tenant}/storage/{path}`).
- validation: required `name` / `sku` / `unit` / `min_stock`; `sku` uniqueness;
  `category_id` / `supplier_id` reject a **trashed** id; `min_stock` integer + blank→0.
- update: changes fields; a new image replaces + deletes the old file; `remove_image` clears
  the image.
- destroy soft-deletes (row hidden from the default query, image file kept).

The `tenant/products/index.tsx` page must exist for the index assertion to pass
(`inertia.testing.ensure_pages_exist = true`) — built alongside the backend.

## 10. Post-build

- `vendor/bin/pint --dirty`, `bun run check`, `bun run types:check`, `php artisan test --compact`.
- Run `php artisan migrate` + `php artisan tenants:migrate` on the **local dev** DBs (tests
  use throwaway DBs), then seed a couple of demo products so the list renders populated.
- Add a **Products** entry to the tenant sidebar nav.
