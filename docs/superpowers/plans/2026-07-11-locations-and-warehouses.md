# Locations & Warehouses restructure — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure inventory so a `Location` (site/branch) is registered first and owns `Warehouse`s that hold stock — replacing today's `Warehouse → Location(bin)` model. Stock, movements, transfers, and the 3 orders move from `location_id` to `warehouse_id`.

**Architecture:** Pre-launch → edit migrations **in place** and `migrate:fresh` + `tenants:migrate` (no data migration). "Everything shifts up a level": today's *warehouse* → *Location(site)*; today's *bin* → *Warehouse*. Deletes are **blocked** (Location with warehouses; Warehouse with on-hand stock) via app-level `deleting` guards. Design: `docs/superpowers/specs/2026-07-11-locations-and-warehouses-design.md`.

**Tech Stack:** Laravel 13 (tenant migrations, Eloquent, `#[Fillable]`, SoftDeletes), spatie/laravel-data DTOs (`#[TypeScript]`), Pest, Inertia v3 + React 19 + TS, Wayfinder routes, Biome, Pint.

**Working rules for every task:** TDD (failing test first). After schema edits run `php artisan migrate:fresh` then `php artisan tenants:migrate` (see the local-DB memory). After DTO changes run `bun run types:generate`. Gate before commit: `php artisan test --compact` (affected) · `vendor/bin/pint --dirty` · `bun run check` · `bun run types:check`. Commit per task.

---

## File Structure

**Migrations (renumber so `locations` precedes `warehouses`; all in `database/migrations/tenant/`):**
- `2026_07_10_000001_create_locations_table.php` — was `create_warehouses`; now the **site** (`name, code(unique), address`).
- `2026_07_10_000002_create_warehouses_table.php` — was `create_locations`; now `location_id, name, code(unique), address`.
- `2026_07_10_000003_create_stock_movements_table.php` — `location_id` → `warehouse_id`.
- `2026_07_10_000004_create_warehouse_stocks_table.php` — was `create_location_stocks`; `location_id` → `warehouse_id`.
- `2026_07_10_000005_create_stock_transfers_table.php` — `from/to_location_id` → `from/to_warehouse_id`.
- `..._000006_create_purchase_orders_table.php` — `received_location_id` → `received_warehouse_id`.
- `..._000008_create_sales_orders_table.php` — `fulfilled_location_id` → `fulfilled_warehouse_id`.
- `..._000011_create_production_orders_table.php` — `completed_location_id` → `completed_warehouse_id`.

**Models (`app/Models/`):** `Location` (site + guard), `Warehouse` (location + address + guard), `WarehouseStock` (was `LocationStock`), `StockMovement`, `StockTransfer`, `PurchaseOrder`, `SalesOrder`, `ProductionOrder`.

**Data (`app/Data/`):** `LocationData`, `WarehouseData`, `WarehouseStockData` (was `LocationStockData`), order DTOs.

**Requests (`app/Http/Requests/Tenant/`):** `LocationRequest`, `WarehouseRequest`, `StockMovementRequest`, `StockTransferRequest`.

**Services/Actions:** `app/Services/StockService.php`; `app/Actions/ReceivePurchaseOrder.php`, `FulfillSalesOrder.php`, `CompleteProductionOrder.php`.

**Controllers (`app/Http/Controllers/Tenant/`):** `LocationController`, `WarehouseController`, `StockMovementController`, `StockTransferController`, `PurchaseOrderController`, `SalesOrderController`, `ProductionOrderController`; `Concerns/BuildsStockPickers.php`.

**Frontend (`resources/js/`):** `pages/tenant/locations/index.tsx`, `pages/tenant/warehouses/index.tsx`, stock/transfer/order pages, `components/tenant/tenant-sidebar.tsx`, `config/resources.ts`.

**Tests (`tests/Feature/Tenant/`):** `LocationTest`, `WarehouseTest`, `StockMovementTest`, `StockTransferTest`, `PurchaseOrderTest`, `SalesOrderTest`, `ProductionOrderTest`, `DashboardTest`.

---

## Task 1: Restructure the schema (migrations)

**Files:** rename + rewrite the 8 migrations listed above.

- [ ] **Step 1: Swap the two file names (git mv, order matters to avoid collision).**

```bash
cd database/migrations/tenant
git mv 2026_07_10_000002_create_locations_table.php 2026_07_10_000002_create_warehouses_table.php
git mv 2026_07_10_000001_create_warehouses_table.php 2026_07_10_000001_create_locations_table.php
git mv 2026_07_10_000004_create_location_stocks_table.php 2026_07_10_000004_create_warehouse_stocks_table.php
cd -
```

- [ ] **Step 2: Rewrite `000001_create_locations_table.php` (the site).**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-tenant site / branch / outlet. Registered first; owns warehouses. Code is
// unique within the tenant (nullable — MySQL permits multiple NULLs).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->nullable()->unique();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
```

- [ ] **Step 3: Rewrite `000002_create_warehouses_table.php` (belongs to a location).**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-tenant warehouse: a stock-holding building at a location. Code is unique
// within the tenant (nullable). Deleting the parent location is blocked in app
// code while warehouses remain (restrictOnDelete backstops hard deletes).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable()->unique();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
```

- [ ] **Step 4: Rewrite `000004_create_warehouse_stocks_table.php`.**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row per warehouse+stockable (compound unique). On-hand lives here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->morphs('stockable');
            $table->decimal('quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'stockable_type', 'stockable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
```

- [ ] **Step 5: Edit `000003_create_stock_movements_table.php`** — change the FK line to:

```php
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
```

- [ ] **Step 6: Edit `000005_create_stock_transfers_table.php`** — change the two FK lines to:

```php
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
```

- [ ] **Step 7: Edit the 3 order migrations** — rename the location FK column in each:

```php
// 000006 purchase_orders:
$table->foreignId('received_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
// 000008 sales_orders:
$table->foreignId('fulfilled_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
// 000011 production_orders:
$table->foreignId('completed_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
```

- [ ] **Step 8: Rebuild the schema on the local dev DBs.**

Run: `php artisan migrate:fresh && php artisan tenants:migrate --force`
Expected: all migrations run, no FK errors (locations before warehouses).

- [ ] **Step 9: Commit.**

```bash
git add -A
git commit -m "feat(inventory): restructure schema to Location(site) -> Warehouse"
```

---

## Task 2: `Location` & `Warehouse` models (+ `WarehouseStock` rename)

**Files:** `app/Models/Location.php`, `Warehouse.php`; rename `LocationStock.php` → `WarehouseStock.php`; `tests/Feature/Tenant/WarehouseTest.php`, `LocationTest.php`.

- [ ] **Step 1: Write failing relationship test** in `WarehouseTest.php`:

```php
it('belongs to a location and exposes warehouse stocks', function () {
    $this->tenant->run(function () {
        $location = App\Models\Location::create(['name' => 'KL HQ']);
        $warehouse = App\Models\Warehouse::create(['location_id' => $location->id, 'name' => 'Main Store']);

        expect($warehouse->location->name)->toBe('KL HQ');
        expect($location->warehouses()->count())->toBe(1);
    });
});
```

- [ ] **Step 2: Run it — expect fail** (`location`/`warehouses` relations/columns don't exist yet).

Run: `php artisan test --filter=WarehouseTest --compact`

- [ ] **Step 3: Rewrite `Location.php`** — site model with `warehouses()` and the delete guard:

```php
#[Fillable(['name', 'code', 'address'])]
class Location extends Model
{
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    protected array $searchable = ['name', 'code', 'address'];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Location $location): void {
            if ($location->warehouses()->exists()) {
                throw new App\Exceptions\BlockedByDependentsException(
                    'Remove this location\'s warehouses before deleting it.'
                );
            }
        });
    }
}
```

- [ ] **Step 4: Rename & rewrite `LocationStock.php` → `WarehouseStock.php`** — `protected $table = 'warehouse_stocks';`, `warehouse(): BelongsTo`, fillable `['warehouse_id', 'stockable_type', 'stockable_id', 'quantity']`. Delete the old file.

- [ ] **Step 5: Rewrite `Warehouse.php`** — add `location_id`/`address` fillable, `location()`, `warehouseStocks()`, `stockMovements()`, and the stock guard:

```php
#[Fillable(['location_id', 'name', 'code', 'address'])]
class Warehouse extends Model
{
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    protected array $searchable = ['name', 'code', 'address'];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Warehouse $warehouse): void {
            if ($warehouse->warehouseStocks()->where('quantity', '!=', 0)->exists()) {
                throw new App\Exceptions\BlockedByDependentsException(
                    'Move or adjust this warehouse\'s stock to zero before deleting it.'
                );
            }
        });
    }
}
```

- [ ] **Step 6: Create `app/Exceptions/BlockedByDependentsException.php`** — a `RuntimeException` subclass rendered as a 422/redirect-with-toast (used by both guards; wired in Task 8).

- [ ] **Step 7: Run the test — expect pass.** Run: `php artisan test --filter=WarehouseTest --compact`

- [ ] **Step 8: `vendor/bin/pint --dirty` · Commit.**

---

## Task 3: `StockMovement`, `StockTransfer`, order models

**Files:** `app/Models/StockMovement.php`, `StockTransfer.php`, `PurchaseOrder.php`, `SalesOrder.php`, `ProductionOrder.php`.

- [ ] **Step 1:** In `StockMovement.php`: fillable `location_id`→`warehouse_id`; `location()`→`warehouse()` as `belongsTo(Warehouse::class)->withTrashed()` (keep the null-safe pattern from the earlier soft-delete fix).
- [ ] **Step 2:** In `StockTransfer.php`: fillable + relations `fromLocation/toLocation`→`fromWarehouse/toWarehouse` (`from_warehouse_id`/`to_warehouse_id`).
- [ ] **Step 3:** In the 3 order models: rename the `received/fulfilled/completedLocation` relation + fillable to `…Warehouse` / `…_warehouse_id`.
- [ ] **Step 4:** Run `php artisan test --filter='StockMovementTest|StockTransferTest' --compact` — expect failures referencing old columns (fixed as tests are updated in later tasks); ensure no *fatal* (undefined method) errors from these models.
- [ ] **Step 5:** `vendor/bin/pint --dirty` · Commit.

---

## Task 4: DTOs + regenerate types

**Files:** `app/Data/LocationData.php`, `WarehouseData.php`; rename `LocationStockData.php`→`WarehouseStockData.php` (if present); order DTOs; then `bun run types:generate`.

- [ ] **Step 1: `LocationData`** — `{ id: int, name: string, code: ?string, address: ?string }`, `fromLocation(Location $l)`. Remove any `warehouse` field.
- [ ] **Step 2: `WarehouseData`** — add `location` (a `LocationData` or `{id,name}` snapshot) + `address`; `fromWarehouse` eager-safe (`$warehouse->location`).
- [ ] **Step 3:** Rename `LocationStockData`→`WarehouseStockData` if it exists; update order DTOs' location snapshot fields → warehouse.
- [ ] **Step 4:** Run `bun run types:generate`. Expected: `generated.d.ts` updates; `App.Data.LocationData`/`WarehouseData` reflect new shapes.
- [ ] **Step 5:** `vendor/bin/pint --dirty` · Commit.

---

## Task 5: FormRequests

**Files:** `LocationRequest.php`, `WarehouseRequest.php`, `StockMovementRequest.php`, `StockTransferRequest.php`.

- [ ] **Step 1: `LocationRequest` rules:**

```php
'name' => ['required', 'string', 'max:255'],
'code' => ['nullable', 'string', 'max:50', Rule::unique('locations', 'code')->ignore($this->route('location'))],
'address' => ['nullable', 'string', 'max:2000'],
```

- [ ] **Step 2: `WarehouseRequest` rules:**

```php
'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->withoutTrashed()],
'name' => ['required', 'string', 'max:255'],
'code' => ['nullable', 'string', 'max:50', Rule::unique('warehouses', 'code')->ignore($this->route('warehouse'))],
'address' => ['nullable', 'string', 'max:2000'],
```

- [ ] **Step 3:** `StockMovementRequest`: `location_id`→`warehouse_id` (`exists('warehouses','id')->withoutTrashed()`). `StockTransferRequest`: `from_location_id`/`to_location_id`→`from_warehouse_id`/`to_warehouse_id` (both exist + `different`).
- [ ] **Step 4:** `vendor/bin/pint --dirty` · Commit.

---

## Task 6: `StockService` + order Actions

**Files:** `app/Services/StockService.php`; `app/Actions/{ReceivePurchaseOrder,FulfillSalesOrder,CompleteProductionOrder}.php`; `tests/Feature/Tenant/StockMovementTest.php`, `StockTransferTest.php`.

- [ ] **Step 1: Update `StockServiceTest`/`StockMovementTest`** to build `Location→Warehouse` and call `record(Warehouse, …)`. Run — expect fail.
- [ ] **Step 2: `StockService`** — replace every `Location $location` param with `Warehouse $warehouse`, `use App\Models\WarehouseStock;`, and in `applyLockedDelta`/`currentQuantity`/`lockedStock`/`writeMovement` use `warehouse_id` + `WarehouseStock`. `transfer(Warehouse $from, Warehouse $to, …)` writes `from_warehouse_id`/`to_warehouse_id`. `setLevel(Warehouse …)`.
- [ ] **Step 3: Actions** — `ReceivePurchaseOrder::handle(PurchaseOrder $order, Warehouse $warehouse)` (records into the warehouse, persists `received_warehouse_id`); same shape for `FulfillSalesOrder` (`fulfilled_warehouse_id`) and `CompleteProductionOrder` (`completed_warehouse_id`).
- [ ] **Step 4:** Run `php artisan test --filter='StockService|StockMovement|StockTransfer' --compact` — expect pass.
- [ ] **Step 5:** `vendor/bin/pint --dirty` · Commit.

---

## Task 7: Controllers + pickers

**Files:** `Concerns/BuildsStockPickers.php`; `LocationController`, `WarehouseController`, `StockMovementController`, `StockTransferController`, `PurchaseOrderController`, `SalesOrderController`, `ProductionOrderController`.

- [ ] **Step 1: `BuildsStockPickers`** — rename `stockLocationOptions()` → `stockWarehouseOptions()`, source `Warehouse::with('location')`, label **`"{location.name} · {warehouse.name}"`** (e.g. `KL HQ · Main Store`):

```php
protected function stockWarehouseOptions(): DataCollection
{
    return WarehouseData::collect(
        Warehouse::with('location')
            ->orderBy('name')
            ->get()
            ->map(fn (Warehouse $w): array => [
                'id' => $w->id,
                'name' => ($w->location?->name ?? '?').' · '.$w->name,
            ]),
        DataCollection::class,
    );
}
```

- [ ] **Step 2: `LocationController`** — drop `->with('warehouse')`; it's now a plain site catalog (`Location::query()->search()->through(LocationData::from)`).
- [ ] **Step 3: `WarehouseController`** — `index` eager-loads `location`; `store`/`update` accept `location_id`; provide a `locations` picker prop (`LocationData::collect(Location::orderBy('name')->get())`) to the page.
- [ ] **Step 4: stock/transfer/order controllers** — swap `stockLocationOptions()`→`stockWarehouseOptions()`, the picker prop names (`locations`→`warehouses`), and the persisted FK (`received_location_id`→`received_warehouse_id`, etc.).
- [ ] **Step 5:** Run `php artisan test --filter='PurchaseOrder|SalesOrder|ProductionOrder' --compact` (update those tests' picker/warehouse fields as needed) — expect pass.
- [ ] **Step 6:** `vendor/bin/pint --dirty` · Commit.

---

## Task 8: Delete guards (block) + tests

**Files:** `app/Exceptions/BlockedByDependentsException.php`, `bootstrap/app.php` (render mapping) or the two controllers' `destroy`; `LocationController`, `WarehouseController`; `LocationTest`, `WarehouseTest`.

- [ ] **Step 1: Write failing guard tests.** `LocationTest`: deleting a location with a warehouse returns an error toast + the location still exists; deleting an empty location succeeds. `WarehouseTest`: deleting a warehouse with on-hand stock is blocked; once stock is zeroed it succeeds.

```php
it('blocks deleting a location that still has warehouses', function () {
    $this->tenant->run(function () {
        $loc = App\Models\Location::create(['name' => 'KL HQ']);
        App\Models\Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        // hit destroy route; assert redirect back with an error toast + row still present
    });
    // assertToast(...) / assertDatabaseHas('locations', ...)
});
```

- [ ] **Step 2:** Render `BlockedByDependentsException` as a redirect-back with an **error toast** (reuse `RespondsWithToast`/the global `useFlashToast`) — either in `bootstrap/app.php` `withExceptions` or a try/catch in each `destroy`. Keep the message from the exception.
- [ ] **Step 3:** Run the guard tests — expect pass.
- [ ] **Step 4:** `vendor/bin/pint --dirty` · Commit.

---

## Task 9: Frontend — pages, pickers, **copy/descriptions**, nav

**Files:** `pages/tenant/locations/index.tsx`, `pages/tenant/warehouses/index.tsx`, stock/transfer/order pages, `components/tenant/tenant-sidebar.tsx`, `config/resources.ts`.

- [ ] **Step 1: `config/resources.ts`** — set meta:
  - `location`: `{ singular: 'location', plural: 'Locations', icon: MapPin }` (a site/branch).
  - `warehouse`: `{ singular: 'warehouse', plural: 'Warehouses', icon: Warehouse }`.

- [ ] **Step 2: `locations/index.tsx`** — site form (`name`, `code`, `address` via `FieldLabel` + hints); table columns name/code/address; **remove** the warehouse column. **Update all copy:** page description → e.g. *"Your sites and branches — each one holds warehouses."*; empty state → *"No locations yet / Add your first site or branch."*; field hints (code = *"A short code for this site, e.g. 'KL' or 'PG'."*, address = the site address).

- [ ] **Step 3: `warehouses/index.tsx`** — add a **Location `ComboboxField`** (`location_id`, required) with hint *"The site this warehouse belongs to."*; keep name/code/address; table shows the parent Location. **Update copy:** page description → *"Warehouses that hold stock, grouped under a location."*; empty state → *"No warehouses yet / Add a warehouse under one of your locations."* Add the **register-first nudge**: when the `locations` picker prop is empty, disable "New warehouse" and show a hint/CTA linking to Locations (*"Create a location first."*).

- [ ] **Step 4: Stock movements / transfers / orders pages** — rename every picker from "location" to **"Warehouse"**: labels + hints + placeholders. Specifically: Stock movements "Location" → "Warehouse"; Stock transfers "From/To" now pick warehouses; PO "Receive into", SO "Fulfil from", Production "At location" → **"At warehouse"**. Update each field's `hint` prose accordingly (e.g. PO receive hint → *"The warehouse received stock is added to."*).

- [ ] **Step 5: `tenant-sidebar.tsx`** — reorder nav so **Locations appears above Warehouses** (register a location first); give Locations an icon (`MapPin`), keep Warehouses (`Warehouse`).

- [ ] **Step 6:** `bun run check` (0 fixes needed) · `bun run types:check` · Commit.

---

## Task 10: Full suite, gate, browser smoke

- [ ] **Step 1: `DashboardTest`** — seed scenario builds `Location → Warehouse`; assert `onHandByWarehouse`, `reorderList`, low-stock, `skus_in_stock` at warehouse level. Convert the stranded-stock test to bypass the guard (force/direct soft-delete) and still assert the on-hand filter excludes it.
- [ ] **Step 2:** Run the full tenant suite: `php artisan test --compact`. Expect green.
- [ ] **Step 3: Gate:** `bun run check:ci` (0 errors) · `bun run types:check` · `bun run build` · `vendor/bin/pint --dirty` (0 issues).
- [ ] **Step 4: Browser smoke** (Playwright at project root, delete after; login `admin@gmail.com`/`password123`, tenant `demo`): register a **Location** → register a **Warehouse** under it → record a **stock movement** into that warehouse → confirm the dashboard "Stock by warehouse" shows it. Try deleting the Location (blocked) and the warehouse-with-stock (blocked). Verify sidebar order + all copy reads "location/warehouse" correctly.
- [ ] **Step 5:** `php artisan migrate:fresh && php artisan tenants:migrate --force` one final time to prove a clean build; reseed demo if applicable. Commit any final fixes.

---

## Self-Review

- **Spec coverage:** every spec section maps to a task — schema (T1), models incl. `WarehouseStock` (T2–T3), DTOs/types (T4), requests (T5), StockService + Actions (T6), controllers/pickers (T7), block guards (T8), frontend + **copy** + nav (T9), tests + on-hand + smoke (T10). ✓
- **Placeholder scan:** migration bodies, guard code, request rules, and picker builder are shown in full; mechanical renames give exact old→new tokens. ✓
- **Type consistency:** `location_id`/`warehouse_id`, `WarehouseStock`, `stockWarehouseOptions()`, `received/fulfilled/completed_warehouse_id`, `BlockedByDependentsException` are named identically across tasks. ✓
- **Migration order:** `locations` (000001) before `warehouses` (000002); FK targets exist when created. ✓
