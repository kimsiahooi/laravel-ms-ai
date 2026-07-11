# Per-warehouse reorder levels + warehouse stock detail — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give each warehouse its own reorder threshold per item, plus a warehouse detail page to view on-hand stock and set those thresholds inline — replacing the global item-level `min_stock`.

**Architecture:** New `warehouse_reorder_levels` tenant table (per warehouse × item). A `WarehouseController::show` builds a two-leg `UNION ALL` over `products`/`raw_materials` (each LEFT-joined to this warehouse's `warehouse_stocks` + `warehouse_reorder_levels`) so every catalog item resolves to `{on_hand, min_stock}` for the warehouse; a `WarehouseReorderLevelController::update` upserts a threshold. The detail page (Inertia/React) shows summary tiles, an In-stock/All-items toggle, and an inline-editable "Min here" column. Item-level `min_stock` is removed everywhere. **No dashboard changes** (its analytics were removed).

**Tech Stack:** Laravel 13, Inertia v3, React 19 + TypeScript, Tailwind v4, spatie/laravel-data, Wayfinder, Pest. stancl/tenancy multi-DB (tenant tables in `database/migrations/tenant/`).

**Spec:** `docs/superpowers/specs/2026-07-11-warehouse-stock-detail-design.md`

---

## File Structure

**Create:**
- `database/migrations/tenant/2026_07_10_000013_create_warehouse_reorder_levels_table.php` — new table.
- `app/Models/WarehouseReorderLevel.php` — model.
- `app/Data/WarehouseItemData.php` — one detail-table row DTO.
- `app/Http/Controllers/Tenant/WarehouseReorderLevelController.php` — threshold upsert.
- `app/Http/Requests/Tenant/WarehouseReorderLevelRequest.php` — validation.
- `resources/js/pages/tenant/warehouses/show.tsx` — the detail page.
- `tests/Feature/Tenant/WarehouseReorderLevelTest.php` — model + update endpoint.
- `tests/Feature/Tenant/WarehouseStockDetailTest.php` — the `show` page + DTO.

**Modify:**
- `database/migrations/tenant/2026_07_09_131116_create_products_table.php` — drop `min_stock`.
- `database/migrations/tenant/2026_07_05_000005_create_raw_materials_table.php` — drop `min_stock`.
- `app/Models/Warehouse.php` — add `reorderLevels()`.
- `app/Models/Product.php` / `app/Models/RawMaterial.php` — remove `min_stock`.
- `app/Data/ProductData.php` / `app/Data/RawMaterialData.php` — remove `min_stock`.
- `app/Http/Requests/Tenant/ProductRequest.php` / `RawMaterialRequest.php` — remove `min_stock`.
- `app/Http/Controllers/Tenant/WarehouseController.php` — add `show`.
- `routes/tenant.php` — add `show` + reorder-levels routes.
- `resources/js/components/data-table.tsx` — add optional `rowHref`.
- `resources/js/pages/tenant/warehouses/index.tsx` — row navigation + name `<Link>`.
- `resources/js/pages/tenant/products/index.tsx` / `raw-materials/index.tsx` — remove `min_stock` field + column.
- `resources/js/pages/tenant/stock-movements/index.tsx` / `stock-transfers/index.tsx` — deep-link pre-scoping.
- Six test fixtures (see Task 2) — strip the dead `min_stock` key.

**Assumptions (verified in the codebase):**
- A morph map maps `product` ⇒ `Product`, `raw_material` ⇒ `RawMaterial` (already used by `WarehouseStock`/`StockMovement`), so `morphs('stockable')` stores those aliases.
- App DB driver is MySQL (so `SUM(CASE …)` for conditional counts, and the pagination tiebreaker matters).
- Concerns exist and are already used by `WarehouseController`: `ResolvesPerPage::perPage()`, `RespondsWithToast::toast()`.
- Tests provision a tenant via `app(ProvisionTenant::class)->handle('Acme','acme','Ada','ada@acme.test','password123')` (fresh migrated tenant DB) and log in via the `loginAsAcmeUser()` helper; models are created directly inside `$this->tenant->run(fn () => …)`.

---

## Task 1: `warehouse_reorder_levels` table + `WarehouseReorderLevel` model

**Files:**
- Create: `database/migrations/tenant/2026_07_10_000013_create_warehouse_reorder_levels_table.php`
- Create: `app/Models/WarehouseReorderLevel.php`
- Modify: `app/Models/Warehouse.php`
- Test: `tests/Feature/Tenant/WarehouseReorderLevelTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Tenant/WarehouseReorderLevelTest.php`:

```php
<?php

use App\Actions\ProvisionTenant;
use App\Models\Location;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseReorderLevel;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('stores a per-warehouse reorder level and defaults min_stock to 0', function () {
    $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        $wh = Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        $level = WarehouseReorderLevel::create([
            'warehouse_id' => $wh->id,
            'stockable_type' => 'product',
            'stockable_id' => $widget->id,
            'min_stock' => 25,
        ]);

        expect((float) $level->min_stock)->toBe(25.0)
            ->and($level->warehouse->name)->toBe('Main')
            ->and($level->stockable->id)->toBe($widget->id);

        $blank = WarehouseReorderLevel::create([
            'warehouse_id' => $wh->id,
            'stockable_type' => 'product',
            'stockable_id' => Product::create(['name' => 'B', 'sku' => 'B-1', 'unit' => 'ea'])->id,
        ]);
        expect((float) $blank->min_stock)->toBe(0.0);
    });
});

it('enforces one reorder level per (warehouse, item)', function () {
    $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        $wh = Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        WarehouseReorderLevel::create([
            'warehouse_id' => $wh->id, 'stockable_type' => 'product',
            'stockable_id' => $widget->id, 'min_stock' => 5,
        ]);

        expect(fn () => WarehouseReorderLevel::create([
            'warehouse_id' => $wh->id, 'stockable_type' => 'product',
            'stockable_id' => $widget->id, 'min_stock' => 9,
        ]))->toThrow(QueryException::class);
    });
});
```

Note: `Product::create` above omits `min_stock` on purpose — after Task 2 the field is gone; Task 1 runs before that removal, but omitting it is valid now (the column defaults to 0) and stays valid after.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=WarehouseReorderLevelTest --compact`
Expected: FAIL — `Class "App\Models\WarehouseReorderLevel" not found` (and/or missing table).

- [ ] **Step 3: Create the migration**

Create `database/migrations/tenant/2026_07_10_000013_create_warehouse_reorder_levels_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-(warehouse, stockable) reorder threshold. Reorder POLICY, kept separate
// from the StockService-owned warehouse_stocks LEDGER so a threshold can exist
// with no stock row. One row per warehouse+stockable (compound unique).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_reorder_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->morphs('stockable');
            $table->decimal('min_stock', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'stockable_type', 'stockable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_reorder_levels');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/WarehouseReorderLevel.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Per-(warehouse, stockable) reorder threshold. Lives on the default connection,
 * which InitializeTenancyByPath has switched to the tenant database.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property string $stockable_type
 * @property int $stockable_id
 * @property string $min_stock
 * @property-read Warehouse $warehouse
 * @property-read Model $stockable
 */
#[Fillable(['warehouse_id', 'stockable_type', 'stockable_id', 'min_stock'])]
class WarehouseReorderLevel extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_stock' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function stockable(): MorphTo
    {
        return $this->morphTo('stockable');
    }
}
```

- [ ] **Step 5: Add the `reorderLevels` relation to `Warehouse`**

In `app/Models/Warehouse.php`, after the existing `warehouseStocks()` method, add:

```php
    /**
     * @return HasMany<WarehouseReorderLevel, $this>
     */
    public function reorderLevels(): HasMany
    {
        return $this->hasMany(WarehouseReorderLevel::class);
    }
```

(`use Illuminate\Database\Eloquent\Relations\HasMany;` is already imported — `warehouseStocks()` uses it.)

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=WarehouseReorderLevelTest --compact`
Expected: PASS (2 tests).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Models/WarehouseReorderLevel.php app/Models/Warehouse.php database/migrations/tenant/2026_07_10_000013_create_warehouse_reorder_levels_table.php tests/Feature/Tenant/WarehouseReorderLevelTest.php
git commit -m "feat(warehouse): add per-warehouse reorder-levels table + model"
```

---

## Task 2: Remove item-level `min_stock` everywhere

Cohesive removal (backend + frontend + fixtures) so the suite ends green and `grep min_stock` returns only the new reorder world.

**Files:**
- Modify: `database/migrations/tenant/2026_07_09_131116_create_products_table.php`, `database/migrations/tenant/2026_07_05_000005_create_raw_materials_table.php`
- Modify: `app/Models/Product.php`, `app/Models/RawMaterial.php`
- Modify: `app/Data/ProductData.php`, `app/Data/RawMaterialData.php`
- Modify: `app/Http/Requests/Tenant/ProductRequest.php`, `app/Http/Requests/Tenant/RawMaterialRequest.php`
- Modify: `resources/js/pages/tenant/products/index.tsx`, `resources/js/pages/tenant/raw-materials/index.tsx`
- Modify tests: `tests/Feature/Tenant/ProductTest.php`, `tests/Feature/Tenant/RawMaterialTest.php`, and six fixtures: `ProductionOrderTest.php`, `SalesOrderTest.php`, `StockTransferTest.php`, `StockMovementTest.php`, `PurchaseOrderTest.php`, `BomTest.php`

- [ ] **Step 1: Update the item tests first (remove `min_stock` expectations)**

In `tests/Feature/Tenant/ProductTest.php`: delete the test `it('rejects a non-integer min_stock', …)` entirely; in `it('creates a product with category, supplier and defaults min_stock to 0', …)` rename to `it('creates a product with category and supplier', …)`, remove the `->and($product->min_stock)->toBe(0)` assertion, and drop any `'min_stock' => …` keys from its payloads; in the update test, drop `'min_stock' => 5` from the payload and the `->and($product->min_stock)->toBe(5)` assertion.

In `tests/Feature/Tenant/RawMaterialTest.php`: rename `it('creates a raw material and defaults min_stock to 0', …)` to `it('creates a raw material', …)`, remove the `->and((float) $rm->min_stock)->toBe(0.0)` assertion; in the update test drop `'min_stock' => 25.5` from the payload and the `min_stock` assertion.

In each of the six fixture files, delete the `'min_stock' => …` key from every `Product::create([...])` / `RawMaterial::create([...])` call:
`ProductionOrderTest.php` (3 calls in the seeder + 1 in the plain-product helper), `SalesOrderTest.php` (1), `StockTransferTest.php` (1), `StockMovementTest.php` (1), `PurchaseOrderTest.php` (2), `BomTest.php` (3).

- [ ] **Step 2: Run the item tests to verify they fail**

Run: `php artisan test --filter="ProductTest|RawMaterialTest" --compact`
Expected: FAIL — the model still has `min_stock` fillable/cast but the tests no longer set it; more importantly this confirms the tests are updated. (They may still pass at this point since the column exists; the real failure will surface after removal in Step 3–6. Proceed regardless.)

- [ ] **Step 3: Drop `min_stock` from the two migrations**

In `database/migrations/tenant/2026_07_09_131116_create_products_table.php`, delete the line:
```php
            $table->unsignedInteger('min_stock')->default(0);
```
In `database/migrations/tenant/2026_07_05_000005_create_raw_materials_table.php`, delete the line:
```php
            $table->decimal('min_stock', 12, 4)->default(0);
```

- [ ] **Step 4: Remove `min_stock` from the models**

In `app/Models/Product.php`: remove `'min_stock'` from the `#[Fillable([...])]` list; delete the `casts()` entry `return ['min_stock' => 'integer'];` — if `casts()` now returns an empty array, keep it returning `[]`; remove the `@property int $min_stock` docblock line.

In `app/Models/RawMaterial.php`: remove `'min_stock'` from `#[Fillable([...])]`; delete the `'min_stock' => 'decimal:4'` cast (keep `casts()` returning `[]` if empty); remove the `@property string $min_stock` docblock line.

- [ ] **Step 5: Remove `min_stock` from the two DTOs**

In `app/Data/ProductData.php`: delete the constructor param `public int $min_stock,` and the `min_stock: $product->min_stock,` line in `fromProduct`.

In `app/Data/RawMaterialData.php`: delete the constructor param `public string $min_stock,` and the `min_stock: $rawMaterial->min_stock,` line in `fromRawMaterial`.

- [ ] **Step 6: Remove `min_stock` from the two FormRequests**

In `app/Http/Requests/Tenant/ProductRequest.php`: delete the whole `prepareForValidation()` method (its only job was `$this->defaultBlankToZero('min_stock')`); delete the `'min_stock' => ['required', 'integer', 'min:0'],` rule; remove the now-unused `use App\Http\Requests\Concerns\NormalizesNumericInput;` import and the `use NormalizesNumericInput;` trait line.

In `app/Http/Requests/Tenant/RawMaterialRequest.php`: same — delete `prepareForValidation()`, the `'min_stock' => ['required', 'numeric', 'min:0'],` rule, the `NormalizesNumericInput` import and trait line.

- [ ] **Step 7: Remove `min_stock` from the two item pages**

In `resources/js/pages/tenant/products/index.tsx`: delete the `const [minStock, setMinStock] = useState('0');` line; delete `setMinStock(String(product.min_stock ?? 0));` in the edit handler and the `setMinStock('0')` reset (if present); delete the `min_stock` column object from the `columns` array; delete the entire `min_stock` form field block (its `<Label>`/`<Input id="min_stock" …>`/`<InputError>`); remove any `min_stock` from the form's submit payload.

In `resources/js/pages/tenant/raw-materials/index.tsx`: same removals (`minStock` state, edit-fill, column, form field).

- [ ] **Step 8: Regenerate DTO types**

Run: `bun run types:generate`
Expected: `resources/js/types/generated.d.ts` no longer has `min_stock` on `ProductData` / `RawMaterialData`.

- [ ] **Step 9: Frontend gates**

Run: `bun run check` then `bun run types:check`
Expected: check fixes formatting; types:check passes with **no** `min_stock` errors. If types:check reports a lingering `min_stock` reference, remove it.

- [ ] **Step 10: Full backend suite green**

Run: `php artisan test --compact`
Expected: PASS (all). Then verify the removal is clean:
Run: `grep -rn "min_stock" app resources/js database routes tests | grep -v node_modules`
Expected: **only** hits under the new `warehouse_reorder_levels` world (the Task 1 migration/model/test) — no `products`/`raw_materials`/DTO/request/page hits.

- [ ] **Step 11: Pint + commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "refactor(inventory): remove global item-level min_stock (moves to per-warehouse levels)"
```

---

## Task 3: Reorder-level write endpoint (`WarehouseReorderLevelController` + request + route)

**Files:**
- Create: `app/Http/Requests/Tenant/WarehouseReorderLevelRequest.php`
- Create: `app/Http/Controllers/Tenant/WarehouseReorderLevelController.php`
- Modify: `routes/tenant.php`
- Test: `tests/Feature/Tenant/WarehouseReorderLevelTest.php` (extend)

- [ ] **Step 1: Add failing endpoint tests**

Append to `tests/Feature/Tenant/WarehouseReorderLevelTest.php`:

```php
it('upserts a reorder level via the endpoint and flashes a toast', function () {
    [$wh, $widget] = $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        $wh = Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        return [$wh, $widget];
    });

    loginAsAcmeUser();
    $url = "/acme/warehouses/{$wh->id}/reorder-levels";

    $this->put($url, ['stockable_type' => 'product', 'stockable_id' => $widget->id, 'min_stock' => 30])
        ->assertRedirect();
    $this->put($url, ['stockable_type' => 'product', 'stockable_id' => $widget->id, 'min_stock' => 45])
        ->assertRedirect();

    $this->tenant->run(function () use ($wh, $widget) {
        $rows = WarehouseReorderLevel::where('warehouse_id', $wh->id)
            ->where('stockable_id', $widget->id)->where('stockable_type', 'product')->get();
        expect($rows)->toHaveCount(1)
            ->and((float) $rows->first()->min_stock)->toBe(45.0);
    });
});

it('accepts min_stock 0 and rejects invalid reorder-level input', function () {
    [$wh, $widget] = $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        $wh = Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        return [$wh, $widget];
    });

    loginAsAcmeUser();
    $url = "/acme/warehouses/{$wh->id}/reorder-levels";

    $this->put($url, ['stockable_type' => 'product', 'stockable_id' => $widget->id, 'min_stock' => 0])
        ->assertRedirect();
    $this->put($url, ['stockable_type' => 'product', 'stockable_id' => $widget->id, 'min_stock' => -1])
        ->assertSessionHasErrors('min_stock');
    $this->put($url, ['stockable_type' => 'widget', 'stockable_id' => $widget->id, 'min_stock' => 5])
        ->assertSessionHasErrors('stockable_type');
    $this->put($url, ['stockable_type' => 'product', 'stockable_id' => 999999, 'min_stock' => 5])
        ->assertSessionHasErrors('stockable_id');
});

it('requires auth for the reorder-level endpoint', function () {
    $wh = $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);

        return Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
    });

    $this->put("/acme/warehouses/{$wh->id}/reorder-levels", [
        'stockable_type' => 'product', 'stockable_id' => 1, 'min_stock' => 5,
    ])->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=WarehouseReorderLevelTest --compact`
Expected: FAIL — route `/acme/warehouses/{id}/reorder-levels` not defined (404/500).

- [ ] **Step 3: Create the FormRequest**

Create `app/Http/Requests/Tenant/WarehouseReorderLevelRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Validation\Rule;

class WarehouseReorderLevelRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Pick the table the stockable_id must exist in, from the (validated) type.
        $table = $this->input('stockable_type') === 'raw_material'
            ? 'raw_materials'
            : 'products';

        return [
            'stockable_type' => ['required', 'in:product,raw_material'],
            'stockable_id' => [
                'required', 'integer',
                Rule::exists($table, 'id')->whereNull('deleted_at'),
            ],
            'min_stock' => ['required', 'numeric', 'min:0'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Tenant/WarehouseReorderLevelController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\WarehouseReorderLevelRequest;
use App\Models\Warehouse;
use App\Models\WarehouseReorderLevel;
use Illuminate\Http\RedirectResponse;

class WarehouseReorderLevelController
{
    use RespondsWithToast;

    public function update(WarehouseReorderLevelRequest $request, Warehouse $warehouse): RedirectResponse
    {
        WarehouseReorderLevel::updateOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'stockable_type' => $request->string('stockable_type'),
                'stockable_id' => $request->integer('stockable_id'),
            ],
            ['min_stock' => $request->float('min_stock')],
        );

        $this->toast('Reorder level updated.');

        return back();
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/tenant.php`, immediately after the `Route::resource('warehouses', …)` block, add:

```php
            Route::put('warehouses/{warehouse}/reorder-levels', [WarehouseReorderLevelController::class, 'update'])
                ->name('warehouses.reorder-levels.update');
```

Add the import near the other `Tenant\…Controller` imports (keep alphabetical):
```php
use App\Http\Controllers\Tenant\WarehouseReorderLevelController;
```

- [ ] **Step 6: Run to verify it passes**

Run: `php artisan test --filter=WarehouseReorderLevelTest --compact`
Expected: PASS (all 5).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/Tenant/WarehouseReorderLevelRequest.php app/Http/Controllers/Tenant/WarehouseReorderLevelController.php routes/tenant.php tests/Feature/Tenant/WarehouseReorderLevelTest.php
git commit -m "feat(warehouse): add reorder-level upsert endpoint"
```

---

## Task 4: `WarehouseItemData` DTO + `WarehouseController::show` (union query) + route

**Files:**
- Create: `app/Data/WarehouseItemData.php`
- Modify: `app/Http/Controllers/Tenant/WarehouseController.php`
- Modify: `routes/tenant.php`
- Test: `tests/Feature/Tenant/WarehouseStockDetailTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Tenant/WarehouseStockDetailTest.php`:

```php
<?php

use App\Actions\ProvisionTenant;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use App\Models\WarehouseReorderLevel;
use App\Services\StockService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/** @return array{0: Warehouse, 1: array<string,int>} the warehouse + item ids */
function seedWarehouseDetail(): array
{
    return test()->tenant->run(function () {
        $stock = app(StockService::class);
        $loc = Location::create(['name' => 'KL HQ']);
        $wh = Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        $other = Warehouse::create(['location_id' => $loc->id, 'name' => 'Overflow']);

        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);
        $gizmo = Product::create(['name' => 'Gizmo', 'sku' => 'G-1', 'unit' => 'ea']);
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg']);
        $idle = Product::create(['name' => 'Idle', 'sku' => 'I-1', 'unit' => 'ea']); // never stocked, no level

        // Main: Widget 140 (fine), Steel 8 with level 20 (alerting), Gizmo 0 with level 5 (out-of-stock alert)
        $stock->record($wh, $widget, 140, StockMovementReason::Adjustment);
        $stock->record($wh, $steel, 8, StockMovementReason::Adjustment);
        WarehouseReorderLevel::create(['warehouse_id' => $wh->id, 'stockable_type' => 'raw_material', 'stockable_id' => $steel->id, 'min_stock' => 20]);
        WarehouseReorderLevel::create(['warehouse_id' => $wh->id, 'stockable_type' => 'product', 'stockable_id' => $gizmo->id, 'min_stock' => 5]);

        // Overflow holds Widget too — must NOT appear on Main's page.
        $stock->record($other, $widget, 500, StockMovementReason::Adjustment);

        return [$wh, ['widget' => $widget->id, 'gizmo' => $gizmo->id, 'steel' => $steel->id, 'idle' => $idle->id]];
    });
}

it('shows this warehouse\'s in-stock and alerting items only, on-hand desc', function () {
    [$wh, $id] = seedWarehouseDetail();
    loginAsAcmeUser();

    $this->get("/acme/warehouses/{$wh->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/warehouses/show')
            ->where('warehouse.name', 'Main')
            // Widget(140), Steel(8, alert), Gizmo(0, alert) — Idle excluded (no stock, no level)
            ->has('items.data', 3)
            ->where('items.data.0.item', 'Widget')       // on_hand desc
            ->where('items.data.0.needs_reorder', false)
            ->where('items.data.1.item', 'Steel')
            ->where('items.data.1.needs_reorder', true)
            ->where('items.data.2.item', 'Gizmo')
            ->where('items.data.2.on_hand', fn ($v) => (float) $v === 0.0)
            ->where('items.data.2.needs_reorder', true)
            ->where('summary.in_stock', 2)               // Widget + Steel (quantity > 0)
            ->where('summary.needs_reorder', 2)          // Steel + Gizmo
        );
});

it('lists all catalog items (incl. unstocked) under ?view=all', function () {
    [$wh, $id] = seedWarehouseDetail();
    loginAsAcmeUser();

    $this->get("/acme/warehouses/{$wh->id}?view=all")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 4) // Widget, Gizmo, Steel, Idle
            ->where('filters.view', 'all')
        );
});

it('keeps search warehouse- and in-stock-scoped', function () {
    [$wh, $id] = seedWarehouseDetail();
    // A zero-stock, no-level product that ALSO matches "Widget" must not leak into
    // the default (in-stock) view via the search OR (guards the OR-precedence bug).
    $this->tenant->run(fn () => Product::create(['name' => 'Widgetoid', 'sku' => 'WO-1', 'unit' => 'ea']));
    loginAsAcmeUser();

    $this->get("/acme/warehouses/{$wh->id}?search=Widget")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)              // Widget only; Widgetoid (0 stock) excluded
            ->where('items.data.0.item', 'Widget')
        );
});

it('excludes a soft-deleted item from items and both summary counts', function () {
    [$wh, $id] = seedWarehouseDetail();

    $this->tenant->run(fn () => Product::find($id['gizmo'])->delete()); // gizmo had an out-of-stock alert
    loginAsAcmeUser();

    $this->get("/acme/warehouses/{$wh->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 2)              // Widget + Steel (Gizmo gone)
            ->where('summary.needs_reorder', 1) // only Steel now
            ->where('summary.in_stock', 2)      // unchanged (Gizmo had 0 on hand)
        );
});

it('paginates deterministically when on_hand ties', function () {
    $wh = test()->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        $wh = Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        // 25 unstocked products all tie at on_hand 0 in ?view=all.
        for ($i = 1; $i <= 25; $i++) {
            Product::create(['name' => "P{$i}", 'sku' => "P-{$i}", 'unit' => 'ea']);
        }

        return $wh;
    });
    loginAsAcmeUser();

    // Collect the row ids across all pages via the where-closure (it receives the
    // actual value and just returns true), then assert none duplicated / dropped.
    $ids = [];
    for ($p = 1; $p <= 3; $p++) {
        $this->get("/acme/warehouses/{$wh->id}?view=all&per_page=10&page={$p}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.data', function ($rows) use (&$ids) {
                    foreach ($rows as $r) {
                        $ids[] = $r['stockable_type'].':'.$r['stockable_id'];
                    }

                    return true;
                }));
    }
    expect(count($ids))->toBe(25)                     // none dropped
        ->and(count(array_unique($ids)))->toBe(25);   // none duplicated across pages
});

it('renders an empty default view for a warehouse with no stock or levels', function () {
    $wh = test()->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']); // catalog exists but unstocked

        return Warehouse::create(['location_id' => $loc->id, 'name' => 'Empty']);
    });
    loginAsAcmeUser();

    $this->get("/acme/warehouses/{$wh->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 0)
            ->where('summary.in_stock', 0)
            ->where('summary.needs_reorder', 0)
        );
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=WarehouseStockDetailTest --compact`
Expected: FAIL — no `show` route / `WarehouseItemData` class.

- [ ] **Step 3: Create the DTO**

Create `app/Data/WarehouseItemData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the warehouse detail table: a catalog item in the context of a
 * warehouse (whether or not it currently holds stock). Built from a raw UNION
 * row (stdClass) — the union carries only the lowercase `stockable_type` alias
 * and string DB scalars, so `type`/`needs_reorder` are derived and numerics cast
 * here. A bare `::from($row)` would THROW (missing type, needs_reorder). Mirrors
 * StockMovementData::fromStockMovement.
 */
#[TypeScript]
class WarehouseItemData extends Data
{
    public function __construct(
        public string $stockable_type, // "product" | "raw_material" (alias — the PUT target)
        public int $stockable_id,
        public string $item,
        public ?string $sku,
        public string $type,           // "Product" | "Raw material" (label)
        public string $unit,
        public float $on_hand,         // quantity in THIS warehouse (0 if none)
        public float $min_stock,       // THIS warehouse's threshold (0 if unset)
        public bool $needs_reorder,    // min_stock > 0 && on_hand < min_stock
    ) {}

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

- [ ] **Step 4: Add `show` to `WarehouseController`**

In `app/Http/Controllers/Tenant/WarehouseController.php`, add imports:
```php
use App\Data\WarehouseItemData;
use Illuminate\Support\Facades\DB;
```
Then add this method (after `index`):

```php
    public function show(Request $request, Warehouse $warehouse): Response
    {
        $warehouse->load('location');

        $search = trim((string) $request->string('search'));
        $view = (string) $request->string('view');
        $perPage = $this->perPage($request);
        $like = '%'.$search.'%';
        $whId = $warehouse->id;

        // One catalog item = one row, with THIS warehouse's on_hand + min_stock
        // left-joined in. Two morph tables → a two-leg UNION ALL. Predicates live
        // in the JOIN ON (an outer where would null out the LEFT JOIN and drop
        // unstocked items). Trashed items are excluded per leg. Rebuilt fresh for
        // the list and the counts so both read from the identical row set.
        $makeUnion = function () use ($whId) {
            $leg = fn (string $table, string $alias) => DB::table($table)
                ->selectRaw("
                    '{$alias}' as stockable_type, {$table}.id as stockable_id,
                    {$table}.name as item, {$table}.sku as sku, {$table}.unit as unit,
                    COALESCE(ws.quantity, 0) as on_hand,
                    COALESCE(rl.min_stock, 0) as min_stock
                ")
                ->whereNull("{$table}.deleted_at")
                ->leftJoin('warehouse_stocks as ws', fn ($j) => $j
                    ->on('ws.stockable_id', '=', "{$table}.id")
                    ->where('ws.stockable_type', $alias)
                    ->where('ws.warehouse_id', $whId))
                ->leftJoin('warehouse_reorder_levels as rl', fn ($j) => $j
                    ->on('rl.stockable_id', '=', "{$table}.id")
                    ->where('rl.stockable_type', $alias)
                    ->where('rl.warehouse_id', $whId));

            return $leg('products', 'product')->unionAll($leg('raw_materials', 'raw_material'));
        };

        $items = DB::query()->fromSub($makeUnion(), 'items')
            // Default view = in stock OR alerting, so out-of-stock alerts (0 < min)
            // still show. Grouped so the search-OR below ANDs onto the whole thing.
            ->when($view !== 'all', fn ($q) => $q->where(fn ($g) => $g
                ->where('on_hand', '>', 0)
                ->orWhere(fn ($r) => $r->where('min_stock', '>', 0)
                    ->whereColumn('on_hand', '<', 'min_stock'))))
            ->when($search !== '', fn ($q) => $q->where(fn ($g) => $g
                ->where('item', 'like', $like)->orWhere('sku', 'like', $like)))
            ->orderByDesc('on_hand')
            ->orderBy('stockable_type')  // deterministic tiebreaker — (type, id) is
            ->orderBy('stockable_id')    // unique across the two UNION legs
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (object $row) => WarehouseItemData::fromRow($row));

        // Full-set counts, derived from the same union so they reconcile with the
        // list by construction (trashed items already excluded per leg).
        $counts = DB::query()->fromSub($makeUnion(), 'items')
            ->selectRaw('
                SUM(CASE WHEN on_hand > 0 THEN 1 ELSE 0 END) as in_stock,
                SUM(CASE WHEN min_stock > 0 AND on_hand < min_stock THEN 1 ELSE 0 END) as needs_reorder
            ')
            ->first();

        return Inertia::render('tenant/warehouses/show', [
            'warehouse' => WarehouseData::from($warehouse),
            'items' => $items,
            'summary' => [
                'in_stock' => (int) ($counts->in_stock ?? 0),
                'needs_reorder' => (int) ($counts->needs_reorder ?? 0),
            ],
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'view' => $view,
            ],
        ]);
    }
```

- [ ] **Step 5: Add `show` to the route + regenerate helpers**

In `routes/tenant.php`, change the warehouses resource `->only([...])` to include `'show'`:
```php
            Route::resource('warehouses', WarehouseController::class)
                ->only(['index', 'store', 'update', 'destroy', 'show']);
```
Then regenerate Wayfinder route helpers (so `warehousesRoutes.show` + the reorder-levels helper exist for the frontend tasks):
Run: `php artisan wayfinder:generate`

- [ ] **Step 6: Run to verify it passes**

Run: `php artisan test --filter=WarehouseStockDetailTest --compact`
Expected: PASS (6 tests). If the `paginates deterministically` test can't read `viewData('page')`, assert instead over the JSON: request with header `X-Inertia: true` and read `items.data` from the decoded body — but `viewData('page')['props']` is the established way Inertia test responses expose props here.

- [ ] **Step 7: Regenerate DTO types, gates, commit**

```bash
bun run types:generate   # adds App.Data.WarehouseItemData
bun run check
vendor/bin/pint --dirty
git add app/Data/WarehouseItemData.php app/Http/Controllers/Tenant/WarehouseController.php routes/tenant.php resources/js tests/Feature/Tenant/WarehouseStockDetailTest.php
git commit -m "feat(warehouse): show detail with per-warehouse on-hand + reorder via union query"
```

---

## Task 5: `DataTable` `rowHref` + row navigation on the Warehouses list

**Files:**
- Modify: `resources/js/components/data-table.tsx`
- Modify: `resources/js/pages/tenant/warehouses/index.tsx`

Frontend — verified by `types:check` + `check` + build (no Pest here).

- [ ] **Step 1: Add optional `rowHref` to `DataTable`**

In `resources/js/components/data-table.tsx`, add `rowHref?: (row: T) => string;` to the `DataTableProps<T>` type and destructure `rowHref` in the component signature. Then replace the row render (the `table.getRowModel().rows.map((row) => ( <TableRow key={row.id}> … ))` block) with a version that wires the guarded click when `rowHref` is present:

```tsx
                                table.getRowModel().rows.map((row) => (
                                    <TableRow
                                        key={row.id}
                                        data-clickable={rowHref ? '' : undefined}
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
                                                          event.button !== 0 ||
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
                                                              'a,button,input,[role="menuitem"],[data-slot="dropdown-menu-trigger"]',
                                                          )
                                                      )
                                                          return;
                                                      router.visit(
                                                          rowHref(row.original),
                                                      );
                                                  }
                                                : undefined
                                        }
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell
                                                key={cell.id}
                                                className={
                                                    cell.column.columnDef.meta
                                                        ?.className
                                                }
                                            >
                                                {flexRender(
                                                    cell.column.columnDef.cell,
                                                    cell.getContext(),
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
```

(`router` is already imported at the top of the file.)

- [ ] **Step 2: Wire the Warehouses list to navigate + link the name**

In `resources/js/pages/tenant/warehouses/index.tsx`:
- The `name` column cell currently renders `<span className="font-medium …">{row.original.name}</span>`. Replace the `<span>` with an Inertia `<Link>` to the detail page (keyboard/new-tab accessible):
```tsx
            cell: ({ row }) => (
                <Link
                    href={warehousesRoutes.show.url({
                        tenant: tenant.slug,
                        warehouse: row.original.id,
                    })}
                    className="font-medium text-foreground hover:underline"
                >
                    {row.original.name}
                </Link>
            ),
```
- Pass `rowHref` to the `<DataTable …>` for whole-row navigation:
```tsx
                rowHref={(warehouse) =>
                    warehousesRoutes.show.url({
                        tenant: tenant.slug,
                        warehouse: warehouse.id,
                    })
                }
```
(`Link` is already imported; `warehousesRoutes` is already imported as `warehousesRoutes`.)

- [ ] **Step 3: Gates**

Run: `bun run check` then `bun run types:check`
Expected: both clean. Then `bun run build` → succeeds.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/data-table.tsx resources/js/pages/tenant/warehouses/index.tsx
git commit -m "feat(warehouse): row-click navigation to warehouse detail (rowHref)"
```

---

## Task 6: The warehouse detail page — `warehouses/show.tsx`

**Files:**
- Create: `resources/js/pages/tenant/warehouses/show.tsx`

Frontend — verified by `types:check`/`check`/`build`, and by the app run in Task 8.

**Reorder-levels route helper:** after Task 4's `wayfinder:generate`, the PUT route generates a helper under `@/routes/tenant/warehouses`. Confirm its exact export by reading the generated `resources/js/routes/tenant/warehouses/index.ts` (Wayfinder camelCases the `reorder-levels` URI segment). Use it as `<helper>.url({ tenant: tenant.slug, warehouse: warehouse.id })`; below it is referenced as `warehousesRoutes.reorderLevels.update`.

- [ ] **Step 1: Create the page**

Create `resources/js/pages/tenant/warehouses/show.tsx`:

```tsx
import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { MapPin, Package, Plus, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { DataTable, type Paginator } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePageProps } from '@/hooks/use-page-props';
import TenantLayout from '@/layouts/tenant-layout';
import { formatQuantity } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/tenant';
import stockMovementsRoutes from '@/routes/tenant/stock-movements';
import stockTransfersRoutes from '@/routes/tenant/stock-transfers';
import warehousesRoutes from '@/routes/tenant/warehouses';
import type { TenantPageProps } from '@/types';

type Warehouse = App.Data.WarehouseData;
type Item = App.Data.WarehouseItemData;

type PageProps = TenantPageProps & {
    warehouse: Warehouse;
    items: Paginator<Item>;
    summary: { in_stock: number; needs_reorder: number };
    filters: { search: string; per_page: number; view: string };
};

// Inline, per-row reorder-level editor. Controlled over local state (seeded from
// the row) so an uncommitted keystroke shows and a dropped refresh doesn't blank
// it. Commits on Enter/blur; spinner is driven by onStart/onFinish (router.put is
// single-flight, so a rapid second commit fires only onCancel/onFinish).
function MinStockCell({ row, warehouseId, tenantSlug }: { row: Item; warehouseId: number; tenantSlug: string }) {
    const [value, setValue] = useState(String(row.min_stock));
    const [saving, setSaving] = useState(false);

    const commit = () => {
        if (value === String(row.min_stock)) return;
        router.put(
            warehousesRoutes.reorderLevels.update.url({
                tenant: tenantSlug,
                warehouse: warehouseId,
            }),
            {
                stockable_type: row.stockable_type,
                stockable_id: row.stockable_id,
                min_stock: value === '' ? 0 : Number(value),
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['items', 'summary'],
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: () => toast.success('Reorder level updated.'),
                onError: () => setValue(String(row.min_stock)),
            },
        );
    };

    return (
        <Input
            type="number"
            min={0}
            inputMode="decimal"
            value={value}
            disabled={saving}
            aria-label={`Reorder level for ${row.item}`}
            onChange={(e) => setValue(e.target.value)}
            onBlur={commit}
            onKeyDown={(e) => {
                if (e.key === 'Enter') e.currentTarget.blur();
            }}
            className="h-8 w-24 text-right tabular-nums"
        />
    );
}

export default function WarehouseShow() {
    const { warehouse, items, summary, filters, tenant } =
        usePageProps<PageProps>();

    const listBase = warehousesRoutes.index.url({ tenant: tenant.slug });
    // View-aware base so the DataTable's own search/per-page reloads keep ?view=all.
    const base = warehousesRoutes.show.url(
        { tenant: tenant.slug, warehouse: warehouse.id },
        filters.view === 'all' ? { query: { view: 'all' } } : undefined,
    );

    const setView = (next: string) => {
        router.get(
            warehousesRoutes.show.url(
                { tenant: tenant.slug, warehouse: warehouse.id },
                {
                    query: {
                        view: next === 'all' ? 'all' : undefined,
                        search: filters.search || undefined,
                        per_page: filters.per_page,
                    },
                },
            ),
            {},
            {
                only: ['items', 'filters'],
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const subline = [warehouse.location, warehouse.code, warehouse.address]
        .filter(Boolean)
        .join(' · ');

    const columns: ColumnDef<Item>[] = [
        {
            accessorKey: 'item',
            header: 'Item',
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span className="font-medium text-foreground">
                        {row.original.item}
                    </span>
                    {row.original.needs_reorder && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Badge
                                    variant="secondary"
                                    className="gap-1 border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400"
                                >
                                    <TriangleAlert className="size-3" />
                                    Reorder
                                </Badge>
                            </TooltipTrigger>
                            <TooltipContent>
                                On hand ({formatQuantity(row.original.on_hand)})
                                is below this warehouse's reorder level (
                                {formatQuantity(row.original.min_stock)}).
                            </TooltipContent>
                        </Tooltip>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'sku',
            header: 'SKU',
            cell: ({ row }) =>
                row.original.sku ? (
                    <span className="font-mono text-muted-foreground text-xs">
                        {row.original.sku}
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            accessorKey: 'type',
            header: 'Type',
            cell: ({ row }) => row.original.type,
            meta: { className: 'text-muted-foreground' },
        },
        {
            accessorKey: 'on_hand',
            header: 'On hand',
            cell: ({ row }) => (
                <span className="tabular-nums">
                    {formatQuantity(row.original.on_hand)}
                </span>
            ),
        },
        {
            id: 'min_here',
            header: 'Min here',
            meta: { className: 'text-right' },
            cell: ({ row }) => (
                <MinStockCell
                    row={row.original}
                    warehouseId={warehouse.id}
                    tenantSlug={tenant.slug}
                />
            ),
        },
        {
            accessorKey: 'unit',
            header: 'Unit',
            cell: ({ row }) => row.original.unit,
            meta: { className: 'text-muted-foreground' },
        },
    ];

    return (
        <TenantLayout
            breadcrumbs={[
                { title: 'Dashboard', href: dashboard.url({ tenant: tenant.slug }) },
                { title: 'Warehouses', href: listBase },
                { title: warehouse.name, href: base },
            ]}
        >
            <Head title={`${warehouse.name} — stock`} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {warehouse.name}
                    </h1>
                    {subline && (
                        <p className="text-muted-foreground text-sm">{subline}</p>
                    )}
                </div>
                <div className="flex shrink-0 gap-2">
                    <Button asChild>
                        <Link
                            href={stockMovementsRoutes.index.url(
                                { tenant: tenant.slug },
                                { query: { warehouse: warehouse.id } },
                            )}
                        >
                            <Plus className="size-4" />
                            Adjust stock
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link
                            href={stockTransfersRoutes.index.url(
                                { tenant: tenant.slug },
                                { query: { from: warehouse.id } },
                            )}
                        >
                            Transfer
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card>
                    <CardContent className="flex items-center gap-3 p-5">
                        <Package className="size-5 text-muted-foreground" />
                        <div>
                            <p className="font-semibold text-2xl tabular-nums">
                                {summary.in_stock}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                In stock
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card
                    className={cn(
                        summary.needs_reorder > 0 &&
                            'border-amber-500/40 bg-amber-500/5',
                    )}
                >
                    <CardContent className="flex items-center gap-3 p-5">
                        <TriangleAlert
                            className={cn(
                                'size-5 text-muted-foreground',
                                summary.needs_reorder > 0 &&
                                    'text-amber-600 dark:text-amber-400',
                            )}
                        />
                        <div>
                            <p className="font-semibold text-2xl tabular-nums">
                                {summary.needs_reorder}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                Needs reorder
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="flex items-center gap-3 p-5">
                        <MapPin className="size-5 text-muted-foreground" />
                        <div>
                            <p className="truncate font-semibold text-lg">
                                {warehouse.location ?? '—'}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                Location
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="flex items-center justify-between gap-3">
                <Tabs
                    value={filters.view === 'all' ? 'all' : 'in_stock'}
                    onValueChange={setView}
                >
                    <TabsList>
                        <TabsTrigger value="in_stock">In stock</TabsTrigger>
                        <TabsTrigger value="all">All items</TabsTrigger>
                    </TabsList>
                </Tabs>
                {filters.view === 'all' && (
                    <p className="text-muted-foreground text-xs">
                        Set a reorder level even for items not yet stocked here.
                    </p>
                )}
            </div>

            <DataTable
                columns={columns}
                paginator={items}
                filters={filters}
                baseUrl={base}
                only={['items', 'filters']}
                getRowId={(item) => `${item.stockable_type}:${item.stockable_id}`}
                title={`${warehouse.name} stock`}
                searchPlaceholder="Search item or SKU…"
                emptyState={
                    <EmptyState
                        icon={Package}
                        title="No stock in this warehouse yet"
                        description="Receive or adjust stock to see it here — or switch to All items to set reorder levels."
                        action={
                            <Button asChild>
                                <Link
                                    href={stockMovementsRoutes.index.url(
                                        { tenant: tenant.slug },
                                        { query: { warehouse: warehouse.id } },
                                    )}
                                >
                                    <Plus className="size-4" />
                                    Adjust stock
                                </Link>
                            </Button>
                        }
                    />
                }
            />
        </TenantLayout>
    );
}
```

- [ ] **Step 2: Reconcile with real APIs**

Confirm against the codebase and fix any mismatch before gating:
- `EmptyState` prop names (`icon`/`title`/`description`/`action`) — match `resources/js/components/empty-state.tsx` and the usage in `warehouses/index.tsx`.
- `TenantLayout` `breadcrumbs` prop shape — match `warehouses/index.tsx`.
- The reorder-levels helper name (`warehousesRoutes.reorderLevels.update`) — match the generated file (Task 6 header note).
- `toast` import path (`sonner`) — match `resources/js/hooks/use-flash-toast.ts`.
- Wayfinder `url(args, { query })` signature — match how query params are passed elsewhere.

- [ ] **Step 3: Gates**

Run: `bun run check` then `bun run types:check` then `bun run build`
Expected: all clean/succeed. Fix any type errors surfaced.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/tenant/warehouses/show.tsx
git commit -m "feat(warehouse): warehouse stock detail page (tiles, view toggle, inline reorder edit)"
```

---

## Task 7: Quick-action deep-links on stock-movements + stock-transfers

**Files:**
- Modify: `resources/js/pages/tenant/stock-movements/index.tsx`
- Modify: `resources/js/pages/tenant/stock-transfers/index.tsx`

- [ ] **Step 1: stock-movements — pre-scope from `?warehouse=`**

In `resources/js/pages/tenant/stock-movements/index.tsx`, add (after the `dialog` is created and `warehouseOptions` exists) a one-shot mount effect. Import `useEffect` from `react`.

```tsx
    // Deep-link: /stock-movements?warehouse={id} opens the create dialog pre-scoped
    // to that warehouse. openCreate() runs the onCreate reset (clears warehouseId),
    // so it must fire BEFORE setWarehouseId — the pre-fill is the last write and
    // wins. One-shot: strip the param so a reload / Back-Forward doesn't reopen a
    // dismissed dialog.
    // biome-ignore lint/correctness/useExhaustiveDependencies: intentional one-time mount effect
    useEffect(() => {
        const id = new URLSearchParams(window.location.search).get('warehouse');
        if (id && warehouseOptions.some((o) => o.value === id)) {
            dialog.openCreate();
            setWarehouseId(id);
            const url = new URL(window.location.href);
            url.searchParams.delete('warehouse');
            window.history.replaceState(window.history.state, '', url);
        }
    }, []);
```

- [ ] **Step 2: stock-transfers — pre-scope from `?from=`**

In `resources/js/pages/tenant/stock-transfers/index.tsx`, add the same shape keyed on `from` → `setFromWarehouseId`:

```tsx
    // biome-ignore lint/correctness/useExhaustiveDependencies: intentional one-time mount effect
    useEffect(() => {
        const id = new URLSearchParams(window.location.search).get('from');
        if (id && warehouseOptions.some((o) => o.value === id)) {
            dialog.openCreate();
            setFromWarehouseId(id);
            const url = new URL(window.location.href);
            url.searchParams.delete('from');
            window.history.replaceState(window.history.state, '', url);
        }
    }, []);
```

Ensure `useEffect` is imported in both files (`import { useEffect, useState } from 'react';`).

- [ ] **Step 3: Gates**

Run: `bun run check` then `bun run types:check` then `bun run build`
Expected: all clean/succeed.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/tenant/stock-movements/index.tsx resources/js/pages/tenant/stock-transfers/index.tsx
git commit -m "feat(warehouse): deep-link Adjust/Transfer pre-scoped to a warehouse"
```

---

## Task 8: Finalize — local migrations, full gates, app verification

**Files:** none (verification only).

- [ ] **Step 1: Re-migrate the local dev databases**

Because two migrations were edited in place, the local central + tenant DBs must be rebuilt (pre-launch, per project convention). Run:
```bash
php artisan migrate:fresh
php artisan tenants:migrate-fresh
```
If `tenants:migrate-fresh` is unavailable, use `php artisan tenants:migrate --fresh` (or drop/recreate the tenant DBs then `tenants:migrate`). Re-seed if a demo seeder is used. Expected: no migration errors; `warehouse_reorder_levels` exists; `products`/`raw_materials` have no `min_stock`.

- [ ] **Step 2: Full gate suite**

```bash
bun run check:ci        # 0 errors
bun run types:check     # clean
vendor/bin/pint --dirty # passed
php artisan test --compact   # all pass
bun run build           # succeeds
```

- [ ] **Step 3: Run the app and verify by hand**

Log in to the demo tenant (`https://laravel-ms-ai.test/demo`, `admin@gmail.com` / `password123`) and:
- Warehouses list → **click a row** → lands on its detail page; the ⋯ Edit/Delete menu still works without navigating; Cmd/Ctrl-click the name opens a new tab.
- Detail page shows the three tiles; the **Needs reorder** tile is amber when > 0.
- Toggle **All items**, then **type in search** and **change per-page** → unstocked rows stay (view survives the reloads); toggle back to **In stock**.
- Edit a **Min here** value, press Enter → success toast; the row's Reorder badge and the Needs-reorder tile update; the count reconciles with the table.
- A rapid second edit doesn't leave the first row's spinner stuck.
- Detail page **Adjust stock** → the stock-movements dialog opens with this warehouse pre-selected; **Transfer** → the transfer dialog opens with this warehouse as the source.
- Both light and dark themes read correctly.

- [ ] **Step 4: Finish the branch**

Announce: "I'm using the finishing-a-development-branch skill to complete this work." Then follow superpowers:finishing-a-development-branch (verify tests, present options, execute choice). Per project convention the work lands on `main`; push only when the user asks.

---

## Notes for the executor

- **TDD:** write each failing test, watch it fail for the stated reason, implement the minimum, watch it pass.
- **Vendored UI is read-only:** all edits are in app components (`data-table.tsx`, pages) — never `components/ui/**`.
- **Wayfinder vs types:** `bun run types:generate` emits DTOs only; `php artisan wayfinder:generate` (or any dev/build) emits route helpers. Both are needed after routes/DTOs change.
- **Order matters** in the deep-link effect (openCreate before setWarehouseId) and in the union query (join predicates in the ON, string cast on `?view`, the pagination tiebreaker) — these were the load-bearing bugs the spec review caught; keep them exactly.
