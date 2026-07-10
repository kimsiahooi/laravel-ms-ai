<?php

use App\Actions\CompleteProductionOrder;
use App\Actions\ProvisionTenant;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/**
 * A workspace with known inventory + order state:
 *  - Widget (min 5) on-hand 2  -> low
 *  - Steel  (min 10) on-hand 30 (22 after a build) -> not low
 *  - Bolt   (min 100) on-hand 0 -> low + out of stock
 *  - Gizmo (BOM: 2 steel/unit): one PENDING MO (qty 1) + one COMPLETED MO (qty 4)
 *  - one pending PO + one pending SO
 */
function seedDashboardScenario(): void
{
    test()->tenant->run(function () {
        $stock = app(StockService::class);
        $wh = Warehouse::create(['name' => 'Main']);
        $loc = Location::create(['warehouse_id' => $wh->id, 'code' => 'A-01']);

        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea', 'min_stock' => 5]);
        $gizmo = Product::create(['name' => 'Gizmo', 'sku' => 'G-1', 'unit' => 'ea', 'min_stock' => 0]);
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg', 'min_stock' => 10]);
        RawMaterial::create(['name' => 'Bolt', 'sku' => 'B-1', 'unit' => 'ea', 'min_stock' => 100]);

        $gizmo->bomItems()->create(['raw_material_id' => $steel->id, 'quantity' => 2]);

        $stock->record($loc, $widget, 2, StockMovementReason::Adjustment);
        $stock->record($loc, $steel, 30, StockMovementReason::Adjustment);

        PurchaseOrder::create(['status' => 'pending', 'currency' => 'USD']);
        SalesOrder::create(['status' => 'pending', 'currency' => 'USD']);

        $mk = function (float $qty, float $required) use ($gizmo, $steel): ProductionOrder {
            $order = ProductionOrder::create([
                'product_id' => $gizmo->id,
                'product_snapshot' => ['name' => 'Gizmo', 'sku' => 'G-1', 'unit' => 'ea'],
                'quantity' => $qty,
                'status' => 'pending',
            ]);
            $order->items()->create([
                'raw_material_id' => $steel->id,
                'raw_material_snapshot' => ['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg'],
                'quantity_per_unit' => 2,
                'quantity_required' => $required,
            ]);

            return $order;
        };

        $mk(1, 2); // stays pending
        app(CompleteProductionOrder::class)->handle($mk(4, 8), $loc); // consumes 8 steel, outputs 4 gizmo
    });
}

it('redirects a guest from the dashboard to the tenant login', function () {
    $this->get('/acme/dashboard')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('renders dashboard aggregates for a seeded workspace', function () {
    seedDashboardScenario();

    loginAsAcmeUser();

    $this->get('/acme/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/dashboard')
            ->where('kpis.open_documents.total', 3)
            ->where('kpis.open_documents.production', 1)
            ->where('kpis.low_stock.count', 2)
            ->where('kpis.low_stock.out_of_stock', 1)
            ->where('kpis.production_in_progress.pending', 1)
            ->where('range.units_made', fn ($v) => (float) $v === 4.0)
            ->where('kpis.skus_in_stock.count', 3)
            ->where('orderPipeline.production.pending', 1)
            ->where('orderPipeline.production.completed', 1)
            ->has('reorderList', 2)
            ->where('reorderList.0.name', 'Bolt') // biggest deficit first
            ->has('stockActivity', now()->day)
            ->has('throughput', now()->day)
            ->has('onHandByWarehouse', 1)
            ->where('onHandByWarehouse.0.name', 'Main')
            ->has('recentMovements')
        );
});

it('drops stock stranded in a soft-deleted location from every on-hand surface', function () {
    test()->tenant->run(function () {
        $warehouse = Warehouse::create(['name' => 'Main']);
        $location = Location::create(['warehouse_id' => $warehouse->id, 'code' => 'A-01']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea', 'min_stock' => 5]);

        // 10 on-hand — comfortably above the min of 5 — then the location holding
        // it is soft-deleted, stranding the stock.
        app(StockService::class)->record($location, $widget, 10, StockMovementReason::Adjustment);
        $location->delete();
    });

    loginAsAcmeUser();

    $this->get('/acme/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.skus_in_stock.count', 0)       // stranded stock is not "in stock"
            ->where('kpis.low_stock.count', 1)           // effective on-hand 0 < min 5
            ->where('kpis.low_stock.out_of_stock', 1)
            ->has('onHandByWarehouse', 0)                // warehouse chart drops it too
            ->has('reorderList', 1)
        );
});

it('renders a zeroed dashboard for a fresh workspace', function () {
    loginAsAcmeUser();

    $this->get('/acme/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/dashboard')
            ->where('kpis.open_documents.total', 0)
            ->where('kpis.low_stock.count', 0)
            ->where('kpis.skus_in_stock.count', 0)
            ->has('reorderList', 0)
            ->has('onHandByWarehouse', 0)
            ->has('stockActivity', now()->day)
        );
});

it('windows the time-series to a requested datetime range and echoes it back', function () {
    loginAsAcmeUser();

    // A fixed 7-day span given as offset-carrying ISO datetimes (no tz param).
    $this->get('/acme/dashboard?from=2026-07-04T00:00:00Z&to=2026-07-10T23:59:59Z')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('range.from', fn ($v) => str_starts_with((string) $v, '2026-07-04'))
            ->where('range.to', fn ($v) => str_starts_with((string) $v, '2026-07-10'))
            ->has('stockActivity', 7)   // one point per day in the span
            ->has('throughput', 7)
            ->where('stockActivity.0.label', 'Jul 4')
            ->where('stockActivity.6.label', 'Jul 10')
        );
});

it('buckets a movement by the offset in the range value, not the UTC day', function () {
    test()->tenant->run(function () {
        $warehouse = Warehouse::create(['name' => 'Main']);
        $location = Location::create(['warehouse_id' => $warehouse->id, 'code' => 'A-01']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea', 'min_stock' => 0]);

        $movement = StockMovement::create([
            'location_id' => $location->id,
            'stockable_type' => 'product',
            'stockable_id' => $widget->id,
            'quantity' => 5,
            'reason' => 'adjustment',
        ]);

        // Pin the stored (UTC) timestamp to 22:30 on Jul 9 — which is 06:30 on
        // Jul 10 in Kuala Lumpur (UTC+8).
        DB::table('stock_movements')->where('id', $movement->id)
            ->update(['created_at' => '2026-07-09 22:30:00']);
    });

    loginAsAcmeUser();

    // A +08:00 Jul-10 window: the movement is 06:30 on Jul 10 there, so it counts.
    $this->get('/acme/dashboard?from=2026-07-10T00:00:00%2B08:00&to=2026-07-10T23:59:59%2B08:00')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('stockActivity', 1)
            ->where('stockActivity.0.in', fn ($v) => (float) $v === 5.0)
        );

    // A UTC Jul-10 window: the same instant is still Jul 9, so it misses it.
    $this->get('/acme/dashboard?from=2026-07-10T00:00:00Z&to=2026-07-10T23:59:59Z')
        ->assertInertia(fn (Assert $page) => $page
            ->where('stockActivity.0.in', fn ($v) => (float) $v === 0.0)
        );
});
