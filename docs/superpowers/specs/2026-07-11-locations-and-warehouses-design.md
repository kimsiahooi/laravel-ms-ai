# Locations & Warehouses restructure — design

**Status:** approved-pending-review · **Date:** 2026-07-11

## Goal

Model the real registration flow: a tenant registers a **Location** (a site /
branch / outlet) first, then registers one or more **Warehouses** under it, and
stock is held **per warehouse**. This replaces today's inverted model where
"Location" means a bin inside a warehouse.

## Decisions (locked with the user)

- **Two levels**, stock lives at the **warehouse**: `Location (site) → Warehouse → stock`. No bins.
- The top-level site entity is named **`Location`** (table `locations`, repurposed).
- A **Warehouse keeps its own `address`** (a warehouse may sit at a different spot than the site's main address).
- A **Warehouse `code` is unique per tenant** (global unique, nullable).
- Deleting a **Location is blocked while it still has warehouses** — remove/reassign them first (safer than cascade). See *Delete semantics*.

## Current → target

```
BEFORE                              AFTER
Warehouse {name,code,address}       Location {name,code,address}      ← the site
  └ Location (bin) {wh_id,code}       └ Warehouse {location_id,name,code,address}
      └ stock @ location_id              └ stock @ warehouse_id
```

Mental model: **everything shifts up one level.** Today's *warehouse* becomes the
*Location/site*; today's *bin-location* becomes the *Warehouse*; stock moves from
the bin to the warehouse.

## Schema (in-place migration edits — pre-launch, user rolls back + remigrates)

Migrations are renumbered so `locations` (the site) is created **before**
`warehouses` (which now FKs to it). All tables are tenant migrations
(`database/migrations/tenant/`).

| # (new) | File | Table | Columns |
|---|---|---|---|
| 000001 | `create_locations_table` | `locations` (site) | `id, name, code(50, nullable, unique), address(text, nullable), timestamps, softDeletes` |
| 000002 | `create_warehouses_table` | `warehouses` | `id, location_id(FK→locations, restrictOnDelete), name, code(50, nullable, unique), address(text, nullable), timestamps, softDeletes` |
| 000003 | `create_stock_movements_table` | `stock_movements` | `location_id` → **`warehouse_id`** (FK→warehouses, restrictOnDelete) |
| 000004 | `create_warehouse_stocks_table` | `warehouse_stocks` (was `location_stocks`) | `id, warehouse_id(FK cascade), morphs(stockable), quantity(15,4), unique(warehouse_id, stockable_type, stockable_id)` |
| 000005 | `create_stock_transfers_table` | `stock_transfers` | `from_location_id/to_location_id` → **`from_warehouse_id/to_warehouse_id`** (FK→warehouses, restrictOnDelete) |
| 000006 | `create_purchase_orders_table` | `purchase_orders` | `received_location_id` → **`received_warehouse_id`** (FK→warehouses, nullOnDelete) |
| 000008 | `create_sales_orders_table` | `sales_orders` | `fulfilled_location_id` → **`fulfilled_warehouse_id`** |
| 000011 | `create_production_orders_table` | `production_orders` | `completed_location_id` → **`completed_warehouse_id`** |

**Renumbering note:** today `warehouses`=000001, `locations`=000002. After the swap,
`locations`(site)=000001 and `warehouses`=000002. The `warehouse_stocks` rename keeps
its 000004 slot. File renames must keep alphabetical order intact.

## Delete semantics — block, not cascade

Deletion is **guarded, not cascaded** — the safe/standard ERP behaviour and the
simplest to reason about (no cascade hooks, no delete/restore symmetry to keep).
All tables use `SoftDeletes`; the guard runs at the app level (a soft delete is
an `UPDATE`, so a DB FK constraint alone can't block it).

- **Location** — cannot be soft-deleted while it still has any non-trashed
  warehouse. A `Location::deleting` guard (mirrored by a check in
  `LocationController::destroy`) aborts with a toast:
  *"This location still has N warehouses — remove them first."*
- **Warehouse → stock** — a Warehouse can't be deleted while it holds on-hand
  stock (any `warehouse_stock` with quantity ≠ 0); zero it out (adjust/transfer)
  first. This supersedes today's "soft-delete strands the stock" behaviour.
- **FKs**: `warehouses.location_id`, `stock_movements.warehouse_id`,
  `stock_transfers.from/to_warehouse_id` → **`restrictOnDelete`** (history and
  parents are never destroyed by a hard delete); `warehouse_stocks.warehouse_id`
  → `cascadeOnDelete` (a force-deleted warehouse drops its ledger rows).
- **Defensive on-hand filter (kept regardless)** — every on-hand surface still
  excludes trashed holders (`DashboardController::onHandMap`'s
  `whereNull(deleted_at)` join + `StockMovement::warehouse()->withTrashed()`
  null-safety), so even a force-deleted/imported edge case can't show phantom stock.

## Models & relationships

- **`Location`** — `#[Fillable(['name','code','address'])]`, `SoftDeletes`,
  `warehouses(): HasMany`. Add a `deleting` guard that blocks while `warehouses()->exists()`.
- **`Warehouse`** — `#[Fillable(['location_id','name','code','address'])]`,
  `SoftDeletes`, `location(): BelongsTo`, `warehouseStocks(): HasMany`,
  `stockMovements(): HasMany`. `deleting` guard blocking while it holds on-hand
  stock (per *Delete semantics*).
- **`LocationStock` → `WarehouseStock`** — rename model + `$table`; `warehouse(): BelongsTo`.
- **`StockMovement`** — `location()` → `warehouse()` (`->withTrashed()`), `warehouse_id` fillable.
- **`StockTransfer`** — `fromLocation/toLocation` → `fromWarehouse/toWarehouse`.
- **Order models** (`PurchaseOrder`, `SalesOrder`, `ProductionOrder`) — the
  `receivedLocation/fulfilledLocation/completedLocation` relations → `…Warehouse`.

## DTOs (`App\Data`)

- `LocationData` — repurpose to `{ id, name, code, address }` (site). `fromLocation`.
- `WarehouseData` — add `location` (`LocationData` or `{id,name}`), `address`.
- `LocationStockData` (if present) → `WarehouseStockData`.
- Order DTOs — the location snapshot fields → warehouse.
- Regenerate: `bun run types:generate` (updates `generated.d.ts`, `App.Data.*`).

## FormRequests

- `LocationRequest` — `name required`, `code nullable|unique(locations,code)`,
  `address nullable`. Drop `warehouse_id`.
- `WarehouseRequest` — add `location_id required|exists(locations,id)`;
  `code nullable|unique(warehouses,code)` (per-tenant); `address nullable`.
- `StockMovementRequest`, `StockTransferRequest` — `location_id`/`from_/to_location_id`
  → `warehouse_id`/`from_/to_warehouse_id`, validated `exists(warehouses,id)`.

## Services & Actions

- **`StockService`** — `record`, `transfer`, `setLevel`, and privates
  (`applyLockedDelta`, `currentQuantity`, `lockedStock`, `writeMovement`) take
  `Warehouse $warehouse` instead of `Location`; use `WarehouseStock`; transfer
  writes `from_warehouse_id/to_warehouse_id`.
- **Actions** `ReceivePurchaseOrder`, `FulfillSalesOrder`, `CompleteProductionOrder`
  — accept a `Warehouse` and record stock against it; persist
  `received/fulfilled/completed_warehouse_id`.

## Controllers & pickers

- **`LocationController`** — now a plain catalog resource for **sites** (name,
  code, address), using the standard catalog pattern (Searchable, per-page,
  toast, DTO `->through()`).
- **`WarehouseController`** — gains a **Location picker** (a `location_id` combobox);
  index eager-loads `location`.
- **`BuildsStockPickers`** — `stockLocationOptions()` → `stockWarehouseOptions()`,
  labelled **"Location · Warehouse"** (e.g. `KL HQ · Main Store`), replacing the
  old "Warehouse · code". Consumed by stock movements/transfers + the 3 orders.
- Stock/transfer/order controllers — swap the picker + the persisted FK.

## Frontend

- **`tenant/locations/index.tsx`** — site form (name, code, address); table shows
  name/code/address; **no** warehouse column. Empty/description copy → "sites / branches".
- **`tenant/warehouses/index.tsx`** — form gains a **Location** `ComboboxField`
  (`hint`: which site this warehouse belongs to); table shows its Location.
- **Stock movements / transfers / orders pages** — every "location" picker label →
  **"Warehouse"**; "Receive into / Fulfil from / At location" now pick a **warehouse**.
- **Sidebar** (`tenant-sidebar`) — order **Locations above Warehouses** (register
  a location first). Update `@/config/resources` meta (singular/plural/icon) for
  both; Location icon e.g. `Building2`/`MapPin`, Warehouse `Warehouse`.
- Dashboard "Stock by warehouse" already groups by warehouse — keep; verify the
  join now reads `warehouses` directly (no bin hop).

## Registration flow

Empty-state guidance: on the Warehouses page with **no locations yet**, the "New
warehouse" action prompts the user to create a Location first (the `location_id`
picker is empty → show a hint/CTA linking to Locations). Locations page has no
such dependency.

## Tests (TDD — update/author before implementing)

- Rename/rewrite `LocationTest` (now a site catalog resource) + `WarehouseTest`
  (now requires a `location_id`, has address).
- **Delete guards**: `LocationTest` — deleting a location with warehouses is
  blocked (error/toast) and succeeds once empty. `WarehouseTest` — deleting a
  warehouse holding stock is blocked and succeeds once zeroed.
- `StockServiceTest`, `StockMovement`/`StockTransfer` feature tests — `Location`→`Warehouse`.
- `ReceivePurchaseOrder`/`FulfillSalesOrder`/`CompleteProductionOrder` tests — warehouse.
- `DashboardTest` — the seed scenario builds `Location → Warehouse`; `onHandByWarehouse`,
  `reorderList`, low-stock, `skus_in_stock` assertions still hold at warehouse level.
  Keep the "stranded stock is excluded from on-hand" test, but set it up by
  **bypassing the delete guard** (force / direct soft-delete) since a normal delete
  of a warehouse-with-stock is now blocked — the defensive filter is still worth proving.
- Confirm the whole tenant suite green under the renamed schema.

## Edge cases & risks

- **Migration order** — `locations` must precede `warehouses`; a wrong file
  number breaks the FK. Verified in the table above.
- **Guards are app-level** — a soft delete is an `UPDATE`, so the delete "block"
  lives in model/controller code; the `restrictOnDelete` FKs only backstop hard
  deletes. `migrate:fresh`/seeders bypass the guards and recreate cleanly.
- **`code` uniqueness** — warehouse `code` is now tenant-global unique; seed/demo
  data must not collide.
- **Type drift** — every column rename must be mirrored in the DTO + regenerated
  TS types, or the frontend breaks at build.

## Out of scope (explicitly not now)

- No bin/shelf sub-location under a warehouse (dropped by decision).
- No per-location users/permissions, no inter-location transfer approvals.
- No data migration of real records (pre-launch; `migrate:fresh` + reseed).
- Security hardening remains deferred (see the standing note).
