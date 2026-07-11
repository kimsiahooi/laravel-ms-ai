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
            // Assert the component NAME only; the page file is built in a later
            // task, so skip the ensure_pages_exist file-existence check here.
            ->component('tenant/warehouses/show', false)
            ->where('warehouse.name', 'Main')
            ->has('items.data', 3) // Widget(140), Steel(8 alert), Gizmo(0 alert); Idle excluded
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
        for ($i = 1; $i <= 25; $i++) { // 25 unstocked products all tie at on_hand 0 in ?view=all
            Product::create(['name' => "P{$i}", 'sku' => "P-{$i}", 'unit' => 'ea']);
        }

        return $wh;
    });
    loginAsAcmeUser();

    // Collect row ids across all pages via the where-closure (it receives the value
    // and just returns true), then assert none duplicated / dropped. NOTE: a plain
    // closure (not an arrow fn) is required here — PHP arrow fns capture by value,
    // so a nested `use (&$ids)` would bind to the arrow's copy and never populate.
    $ids = [];
    for ($p = 1; $p <= 3; $p++) {
        $this->get("/acme/warehouses/{$wh->id}?view=all&per_page=10&page={$p}")
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids) {
                $page->where('items.data', function ($rows) use (&$ids) {
                    foreach ($rows as $r) {
                        $ids[] = $r['stockable_type'].':'.$r['stockable_id'];
                    }

                    return true;
                });
            });
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
