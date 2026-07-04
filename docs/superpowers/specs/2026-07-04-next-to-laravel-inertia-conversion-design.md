# Design: Convert `next-ms-ai` → `laravel-ms-ai` (Laravel + Inertia + React)

**Date:** 2026-07-04
**Status:** Draft for review
**Source project:** `/Users/jasonooi/Documents/next-ms-ai` (Next.js 16 App Router + Prisma + MySQL + better-auth)
**Target project:** `/Users/jasonooi/Herd/laravel-ms-ai` — a Herd site served at `laravel-ms-ai.test` (Laravel 13 + Inertia v3 + React 19 + TypeScript)
**Versions verified 2026-07-04:** Laravel 13.18.1 (released 2026-07-02), Inertia.js v3 (released 2026-03-26). `laravel new` installs whatever is latest, so these are what scaffolding will pull.

---

## 1. Goal

Rewrite the existing **multi-tenant manufacturing / inventory system** from Next.js to a
Laravel + Inertia + React stack. Priorities (from the user):

- **Simple and easy to maintain** — this project only; no over-engineering.
- **Nicer, more UX-friendly UI** — a visual/UX upgrade, not a 1:1 copy. Built with the
  frontend-design skill.
- **Follow Laravel best practice** — idiomatic Laravel/Eloquent/Inertia over faithfully
  mirroring the Next.js implementation.
- **Multi-tenancy** via `archtechx/tenancy` in **multi-database mode**, identified by **path**
  (URL slug).
- **Images** stored with **native Laravel Storage**.

## 2. What the source app is

A single-database (today), row-scoped multi-tenant **MRP-lite ERP**: each organization manages a
product/material catalog, bills of materials, purchase/sales/production orders, and warehouse
stock. Two UI areas: a per-org dashboard at `/{slug}/…` and a central super-admin panel at
`/admin` that provisions tenants and manages members/users. Auth is better-auth (organization +
admin plugins). Uploads are already proxied to an external Laravel asset service. Roles exist on
membership but are **not enforced**. Tables ship whole datasets and paginate client-side.

Domain entities: category, supplier, customer, product (catalog-only; stock derived),
raw_material, bom_item, purchase_order(+items), sales_order(+items), production_order(+items),
stock_movement, warehouse, location, location_stock, stock_transfer.

## 3. Decisions (locked in this brainstorm)

| # | Decision | Choice |
|---|----------|--------|
| 1 | Data | **Greenfield rebuild** — clean schema + fresh seeders. No data migration. |
| 2 | Tenancy | **archtechx/tenancy, multi-database mode**, **path identification by slug**. |
| 3 | Users | **Two separate user worlds** (see §4). Central users = super-admins only; each tenant DB has its own users. |
| 4 | Multi-org membership | **Dropped.** A person needing two orgs has two separate accounts (one per tenant DB). |
| 5 | Roles / permissions | **Deferred.** Any authenticated tenant user has full access to their tenant. No `role` column yet; can add one to tenant `users` later. |
| 6 | Fidelity | **Follow Laravel best practice**, not the Next.js structure. |
| 7 | Primary keys | **`bigint` auto-increment** everywhere, including the `organizations` (tenant) record — path resolves by a `slug` resolver (Approach B), so a stable numeric id gives `tenant_<id>` DB names and slugs can change without orphaning a database. (`config('tenancy.id_generator')=null` to keep integer ids.) |
| 8 | "product OR raw material" refs | **Polymorphic `morphTo`** (`stockable`) with a morph map (`product`, `raw_material`). |
| 9 | Stock model | **Unified ledger + materialized balance**, and **fix the two source bugs** (see §6). *User to re-check later.* |
| 10 | Scaffold | `laravel new laravel-ms-ai` → **React starter kit** (latest = Laravel 13 + Inertia v3 + React 19 + TS + Tailwind v4 + built-in auth + Radix/shadcn components). |

## 4. Architecture — tenancy & auth

### Two databases, two user worlds

**Central (landlord) DB — one:**
- `users` — **super-admins only**; log in **only** at `/admin`. Being in this table *is* the privilege (no role flag needed).
- `organizations` — the **tenants** (name, unique `slug`, logo, `database`, soft-delete). Backs archtechx's tenants table.
- Central `sessions`, `jobs`, `cache`, `password_reset_tokens`.
- No membership pivot — multi-org membership no longer exists.

**Per-tenant DB — one per organization, auto-created on provisioning:**
- `users` — **that tenant's own users**; log in **only** at `/{slug}/login`.
- All business tables. **No `organization_id` column** anywhere — the database *is* the tenant boundary.
- Audit `user_id` columns are real in-DB FKs to the tenant's own `users`.

### Routing & isolation
- `/{slug}/…` → `InitializeTenancyByPath` resolves the org **by slug**, switches the DB connection.
  Requires an authenticated **tenant** user.
- `/admin/…`, `/login`, auth routes → central connection, **outside** the tenant group.
- Reserved slugs (`admin`, `login`, `api`, `storage`, etc.) are blocked so a tenant path can't
  shadow a central route.
- **Auth/session isolation:** the tenant session + auth cookie are scoped per tenant (archtechx
  per-tenant auth recipe) so a login on tenant A is never valid on tenant B or central. Middleware
  ordering (`StartSession` vs tenancy init) is handled explicitly in the implementation plan.

### Provisioning flow (super-admin creates an org)
1. Create `organizations` row (name, slug) → archtechx creates the tenant **database**.
2. Run tenant migrations (`tenants:migrate`) + seed baseline data.
3. Create the **initial tenant user** (email + password) so someone can log in.
4. Additional tenant users are managed **inside** the tenant at `/{slug}/users` (any tenant user,
   for now — roles deferred).

### Impersonation
Super-admin can "log in as" a tenant user via archtechx's built-in impersonation; an `impersonating`
flag is shared to the UI via Inertia.

## 5. Data model

**Cross-cutting conventions:** `bigint` PKs; `SoftDeletes` on parents (not on immutable
line/ledger rows); money `decimal(12,2)`, quantities `decimal(12,4)`, finished-goods counts
`integer`; PHP 8 backed **enums** for statuses/types (DB column stays string); JSON **snapshot**
columns cast to `array`, populated in services at write time; composite uniques that used
`organization_id` collapse to simple uniques (the DB is the tenant).

### Central DB
- `users` (name, email unique, password, avatar, timestamps, soft-delete)
- `organizations` (name, slug unique, logo, database, timestamps, soft-delete)
- `sessions`, `jobs`, `cache`, `password_reset_tokens`

### Per-tenant DB
- **Identity:** `users` (name, email unique, password, avatar, soft-delete)
- **Catalog:** `categories`; `suppliers`; `customers`;
  `products` (name, sku unique, barcode, description, category_id nullOnDelete, supplier_id
  nullOnDelete, min_stock int, unit, image, soft-delete);
  `raw_materials` (name, sku unique, unit, min_stock decimal, soft-delete)
- **BOM:** `bom_items` (product_id, raw_material_id, quantity; unique(product_id, raw_material_id))
- **Procurement:** `purchase_orders` (status enum, supplier_id, user_id, notes, soft-delete) →
  `purchase_order_items` (raw_material_id, raw_material_snapshot json, quantity, unit_cost, currency)
- **Sales:** `sales_orders` (status enum, customer_id nullOnDelete, user_id, notes, soft-delete) →
  `sales_order_items` (product_id, product_snapshot json, quantity int, unit_price, currency)
- **Manufacturing:** `production_orders` (product_id, product_snapshot json, quantity, status enum,
  user_id, notes, soft-delete) →
  `production_order_items` (raw_material_id, raw_material_snapshot json, quantity_required)
- **Inventory:** `stock_movements` (unified ledger — see §6); `warehouses`;
  `locations` (warehouse_id, code; unique(warehouse_id, code));
  `location_stocks` (location_id, `stockable` morphTo, quantity);
  `stock_transfers` (from_location_id, to_location_id, `stockable` morphTo, quantity, user_id)

## 6. Business logic

### Standard CRUD pattern (idiomatic Laravel + Inertia)
- One **resource controller** per entity → returns an **Inertia page**.
- Validation in **Form Requests**; success → redirect back with a **flash** → React toast.
- **Server-side** pagination + search + sort + filter (Laravel paginator → Inertia props),
  replacing the source's ship-everything-and-paginate-client-side.

### Unified inventory model (decision #9 — replaces the source's 3 inconsistent systems)
- **One append-only ledger** `stock_movements` is the source of truth for **any stockable**
  (product or raw material, via the morph map), each row typed by reason:
  `purchase_receipt`, `sales_fulfillment`, `production_consume`, `production_output`,
  `transfer_in`, `transfer_out`, `adjustment`.
- **Materialized on-hand** `location_stocks`, updated **in the same DB transaction** as every
  ledger write. On-hand is never recomputed ad hoc.
- **All mutations go through service classes** wrapped in `DB::transaction` + `lockForUpdate`;
  availability checked before any OUT; negative stock rejected.
- **Fixes vs source:** PO-receive now writes a ledger entry; sales-fulfill now updates location
  stock. (User will re-check this behavior change later.)

### State machines (transactional service actions)
- **Purchase Order:** editable while `PENDING` → **Receive** posts `purchase_receipt` IN for each
  raw material → `RECEIVED`; or `CANCELLED`.
- **Sales Order:** `PENDING` → **Fulfill** (checks product availability) posts `sales_fulfillment`
  OUT + decrements location stock → `FULFILLED`; or `CANCELLED`.
- **Production Order:** create explodes the product's **BOM** into items (`DRAFT`) → **Complete**
  (checks raw-material availability) posts `production_consume` OUT + `production_output` IN →
  `COMPLETED`; or `CANCELLED`.
- **Stock Transfer:** atomic OUT(source) + IN(destination) for one stockable.
- **Stock Movement:** manual IN/OUT/ADJUSTMENT (OUT blocked if it would go negative).
- **BOM edit** re-syncs `quantity_required` + snapshots across that product's `DRAFT` production
  orders (domain event / service call).
- **Snapshots** populated by the service at write time.

## 7. Files & printing
- **Native Laravel Storage** replaces the external asset service. Images: product photos, user
  avatars (tenant + central), org logos.
- **Per-tenant isolation:** tenant uploads under that tenant's storage folder (archtechx
  filesystem tenancy); central uploads on a separate central folder.
- Upload = Inertia form → controller validates (image, max size) → `Storage::put` → save path →
  URL accessor for display.
- **Print/invoice pages** (PO & SO) → print-styled Inertia pages now; PDF later if desired.

## 8. UI/UX plan (nicer + friendlier)
Keep the starter kit's Radix/shadcn system + dark mode; level it up with the frontend-design skill:
- **Persistent app shell** — collapsible sidebar + topbar (no flicker between pages), mobile
  drawer, keyboard toggle, breadcrumbs.
- **Real dashboards** — KPI stat-tiles (stock value, low-stock items, open orders, production in
  progress) + trend charts (built with the dataviz skill). Both org and (lighter) central admin.
- **Better tables** — server-side search/sort/filter, sticky toolbar, column show/hide, skeleton
  loading, friendly empty states, clear row actions.
- **Friendlier forms & flows** — inline validation, obvious toasts, slide-over/dialog create-edit,
  low-stock badges, status chips, tabbed detail pages (product→BOM, customer→sales history,
  supplier→purchases, warehouse→locations).
- **Light brand accent** instead of pure grayscale; refined spacing/typography; consistent
  light + dark.

## 9. Feature module inventory (screens)

**Central `/admin`:** login; dashboard (light KPIs); organizations list + create/edit/soft-delete
+ detail (provision DB, seed first user, impersonate); super-admin profile settings.

**Tenant `/{slug}`:** login; dashboard (KPIs + charts); products (+ detail w/ BOM tab); categories;
suppliers (+ purchase history); customers (+ sales history); raw materials; purchase orders
(+ receive, + print); sales orders (+ fulfill, + print); production orders (+ complete);
stock movements; warehouses (+ locations); locations; stock transfers; users; profile settings.

## 10. Build phases
1. **Foundation** — scaffold; multi-DB tenancy by slug; central + tenant auth isolation; org
   provisioning; reserved slugs.
2. **Catalog** — categories, suppliers, customers, products, raw materials.
3. **Inventory core** — warehouses, locations, unified ledger + stock services, movements,
   transfers.
4. **Orders** — purchase orders (receive), sales orders (fulfill).
5. **Manufacturing** — BOM, production orders (complete), BOM→production explosion & re-sync.
6. **Dashboards + polish** — KPIs, charts, print/invoice pages, final UI/UX pass.

Each phase can get its own implementation plan; this document is the master spec.

## 11. Out of scope (for now)
- Roles / permissions enforcement (structure left ready; deferred).
- Migrating existing production data (greenfield rebuild).
- OAuth / social login (`account` table dropped; email + password only).
- Cross-tenant reporting in the central panel (multi-DB makes this a later, deliberate feature).

## 12. Risks / to confirm later
- **Session/auth isolation** on one domain with path-based tenancy (middleware ordering) — the
  fiddliest part; handled in the plan, worth verifying end-to-end.
- **Stock behavior change** (decision #9) — user to re-check the unified-ledger behavior vs the
  original.
- **Multi-DB operational cost** — migrations/seeders run per tenant; provisioning creates a DB;
  backups are per-tenant. Accepted trade-off for hard isolation.
- **`laravel new` into the existing empty folder** — use `--force` (or scaffold in a temp dir and
  move in) while preserving `docs/`.
