<?php

use App\Actions\ProvisionTenant;
use App\Enums\ProductionOrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Models\WarehouseReorderLevel;
use App\Services\InventoryValuationService;
use App\Services\StockReportService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/** A warehouse to hold stock in. Name it so a second one is distinguishable. */
function valuationWarehouse(string $name = 'Main'): Warehouse
{
    $location = Location::firstOrCreate(['name' => 'KL HQ']);

    return Warehouse::create(['location_id' => $location->id, 'name' => $name]);
}

/** Record a received purchase of $qty at $unitCost, in $currency at $rate. */
function valuationPurchase(RawMaterial $material, float $qty, float $unitCost, float $rate = 1.0): void
{
    $po = PurchaseOrder::create([
        'supplier_id' => null,
        'status' => PurchaseOrderStatus::Received,
        'currency' => $rate === 1.0 ? 'MYR' : 'USD',
        'exchange_rate' => $rate,
        'received_at' => now(),
    ]);

    $po->items()->create([
        'raw_material_id' => $material->id,
        'raw_material_snapshot' => ['name' => $material->name, 'sku' => $material->sku, 'unit' => $material->unit],
        'quantity' => $qty,
        'unit_cost' => $unitCost,
    ]);
}

/** Put $qty of $item into $warehouse. */
function valuationStock(Warehouse $warehouse, Product|RawMaterial $item, float $qty): void
{
    app(StockService::class)->record(
        $warehouse, $item, $qty, StockMovementReason::Adjustment,
    );
}

it('values raw material stock at the weighted average of what was paid', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);
        $warehouse = valuationWarehouse();

        // 10 @ 5.00 then 30 @ 9.00 → weighted average 8.00 (not the 7.00 mean).
        valuationPurchase($steel, 10, 5.0);
        valuationPurchase($steel, 30, 9.0);
        valuationStock($warehouse, $steel, 40);

        $value = app(InventoryValuationService::class)->onHand();

        expect($value->value)->toBe(320.0)   // 40 × 8.00
            ->and($value->valued)->toBe(1)
            ->and($value->unvalued)->toBe(0);
    });
});

it('converts a foreign-currency purchase to base currency before averaging', function () {
    $this->tenant->run(function () {
        $resin = RawMaterial::create(['name' => 'Resin', 'sku' => 'RS-1', 'unit' => 'kg']);
        $warehouse = valuationWarehouse();

        // Same material bought in two currencies: 10 @ MYR 9.00, then 10 @ USD 2.00
        // at rate 4.5 (= MYR 9.00). Each line must be converted *before* averaging —
        // averaging first and converting after would price this at MYR 24.75/kg.
        valuationPurchase($resin, 10, 9.0);
        valuationPurchase($resin, 10, 2.0, 4.5);
        valuationStock($warehouse, $resin, 20);

        expect(app(InventoryValuationService::class)->onHand()->value)->toBe(180.0);
    });
});

it('prices stock only from purchases that actually arrived', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);
        $warehouse = valuationWarehouse();

        valuationPurchase($steel, 10, 5.0);      // received → counts
        valuationStock($warehouse, $steel, 10);

        // Goods that haven't arrived, and an order that was deleted, must not drag
        // the cost basis around — only received lines describe what was paid.
        foreach ([PurchaseOrderStatus::Pending, PurchaseOrderStatus::Cancelled] as $status) {
            $po = PurchaseOrder::create([
                'status' => $status, 'currency' => 'MYR', 'exchange_rate' => 1,
            ]);
            $po->items()->create([
                'raw_material_id' => $steel->id,
                'raw_material_snapshot' => ['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg'],
                'quantity' => 100, 'unit_cost' => 50,
            ]);
        }

        $deleted = PurchaseOrder::create([
            'status' => PurchaseOrderStatus::Received, 'currency' => 'MYR',
            'exchange_rate' => 1, 'received_at' => now(),
        ]);
        $deleted->items()->create([
            'raw_material_id' => $steel->id,
            'raw_material_snapshot' => ['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg'],
            'quantity' => 100, 'unit_cost' => 50,
        ]);
        $deleted->delete();

        // Still 10 × 5.00, not re-priced by the 50.00 lines.
        expect(app(InventoryValuationService::class)->onHand()->value)->toBe(50.0);
    });
});

it('values a finished product by rolling up its bill of materials', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);
        $bolt = RawMaterial::create(['name' => 'Bolt', 'sku' => 'BO-1', 'unit' => 'pcs']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        $warehouse = valuationWarehouse();

        valuationPurchase($steel, 100, 3.0);   // 3.00 / kg
        valuationPurchase($bolt, 100, 0.5);    // 0.50 / pc
        $widget->bomItems()->create(['raw_material_id' => $steel->id, 'quantity' => 2]);
        $widget->bomItems()->create(['raw_material_id' => $bolt->id, 'quantity' => 4]);

        // Each widget costs 2×3.00 + 4×0.50 = 8.00.
        valuationStock($warehouse, $widget, 5);

        $value = app(InventoryValuationService::class)->onHand();

        expect($value->value)->toBe(40.0)->and($value->valued)->toBe(1);
    });
});

it('leaves stock it cannot cost out of the total and reports it as unvalued', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);
        $mystery = RawMaterial::create(['name' => 'Mystery', 'sku' => 'MY-1', 'unit' => 'kg']);
        $warehouse = valuationWarehouse();

        valuationPurchase($steel, 10, 4.0);
        valuationStock($warehouse, $steel, 10);
        // Never purchased, so there is nothing to derive a cost from.
        valuationStock($warehouse, $mystery, 999);

        $value = app(InventoryValuationService::class)->onHand();

        expect($value->value)->toBe(40.0)
            ->and($value->valued)->toBe(1)
            ->and($value->unvalued)->toBe(1);
    });
});

it('will not half-price a product whose bill of materials is only partly costed', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);
        $mystery = RawMaterial::create(['name' => 'Mystery', 'sku' => 'MY-1', 'unit' => 'kg']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        $warehouse = valuationWarehouse();

        valuationPurchase($steel, 100, 3.0);
        $widget->bomItems()->create(['raw_material_id' => $steel->id, 'quantity' => 2]);
        $widget->bomItems()->create(['raw_material_id' => $mystery->id, 'quantity' => 1]);
        valuationStock($warehouse, $widget, 5);

        // Counting only the steel would under-value every widget, so it's unvalued.
        $value = app(InventoryValuationService::class)->onHand();

        expect($value->value)->toBe(0.0)
            ->and($value->valued)->toBe(0)
            ->and($value->unvalued)->toBe(1);
    });
});

it('ignores stock held in a deleted warehouse', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);
        $live = valuationWarehouse('Live');
        $gone = valuationWarehouse('Closed');

        valuationPurchase($steel, 10, 4.0);
        valuationStock($live, $steel, 10);
        valuationStock($gone, $steel, 50);

        // The app won't let you delete a warehouse that still holds stock, so this
        // retires it behind the model's back — the valuation must still not count
        // stock sitting in a warehouse that is no longer part of the business.
        DB::table('warehouses')->where('id', $gone->id)->update(['deleted_at' => now()]);

        expect(app(InventoryValuationService::class)->onHand()->value)->toBe(40.0);
    });
});

it('separates items that have run out from those merely low', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);
        $bolt = RawMaterial::create(['name' => 'Bolt', 'sku' => 'BO-1', 'unit' => 'pcs']);
        $warehouse = valuationWarehouse();

        WarehouseReorderLevel::create([
            'warehouse_id' => $warehouse->id, 'stockable_type' => 'raw_material',
            'stockable_id' => $steel->id, 'min_stock' => 10,
        ]);
        WarehouseReorderLevel::create([
            'warehouse_id' => $warehouse->id, 'stockable_type' => 'raw_material',
            'stockable_id' => $bolt->id, 'min_stock' => 10,
        ]);

        valuationStock($warehouse, $steel, 4);   // low, but present
        // Bolt is left at nothing at all → out of stock.

        $reports = app(StockReportService::class);

        expect($reports->lowStockCount())->toBe(2)
            ->and($reports->outOfStockCount())->toBe(1);
    });
});

it('counts one item low in several warehouses as one item to reorder', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);

        // The same material tracked in three warehouses, empty in all of them.
        foreach (['North', 'South', 'East'] as $name) {
            $warehouse = valuationWarehouse($name);
            WarehouseReorderLevel::create([
                'warehouse_id' => $warehouse->id, 'stockable_type' => 'raw_material',
                'stockable_id' => $steel->id, 'min_stock' => 10,
            ]);
        }

        $reports = app(StockReportService::class);

        // One item has run out — not three, which is what counting rows would say.
        expect($reports->lowStockCount())->toBe(1)
            ->and($reports->outOfStockCount())->toBe(1);
    });
});

it('does not call a purchase order late on the day it is due', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);

        // Due today (stored at midnight) and due yesterday.
        foreach ([now()->startOfDay(), now()->subDay()->startOfDay()] as $due) {
            $po = PurchaseOrder::create([
                'status' => PurchaseOrderStatus::Pending, 'currency' => 'MYR',
                'exchange_rate' => 1, 'expected_date' => $due,
            ]);
            $po->items()->create([
                'raw_material_id' => $steel->id,
                'raw_material_snapshot' => ['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg'],
                'quantity' => 1, 'unit_cost' => 1,
            ]);
        }

        // Only yesterday's is late; today's still has the whole day to arrive.
        expect(app(StockReportService::class)->openPurchaseTotals()->overdue)->toBe(1);
    });
});

it('adds up a product listed twice on the same order before calling it shippable', function () {
    $this->tenant->run(function () {
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        $warehouse = valuationWarehouse();

        $order = SalesOrder::create([
            'customer_id' => null, 'status' => SalesOrderStatus::Pending, 'currency' => 'MYR',
        ]);
        // Two lines of 6 — 12 in total, which no single line reveals.
        foreach ([6, 6] as $qty) {
            $order->items()->create([
                'product_id' => $widget->id,
                'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs'],
                'quantity' => $qty, 'unit_price' => 10,
            ]);
        }

        $reports = app(StockReportService::class);

        valuationStock($warehouse, $widget, 8);   // covers either line, not both
        expect($reports->salesOrdersReadyToShip())->toBe(0);

        valuationStock($warehouse, $widget, 4);   // now 12 in stock
        expect($reports->salesOrdersReadyToShip())->toBe(1);
    });
});

it('never calls an order shippable when its product has been deleted', function () {
    $this->tenant->run(function () {
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        $warehouse = valuationWarehouse();

        $order = SalesOrder::create([
            'customer_id' => null, 'status' => SalesOrderStatus::Pending, 'currency' => 'MYR',
        ]);
        $order->items()->create([
            'product_id' => $widget->id,
            'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs'],
            'quantity' => 1, 'unit_price' => 10,
        ]);
        valuationStock($warehouse, $widget, 50);

        $reports = app(StockReportService::class);
        expect($reports->salesOrdersReadyToShip())->toBe(1);

        // Fulfil refuses an order whose product is gone, so it isn't "ready".
        $widget->delete();
        expect($reports->salesOrdersReadyToShip())->toBe(0);
    });
});

it('blocks a build whose material has been deleted', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        $warehouse = valuationWarehouse();

        $order = ProductionOrder::create([
            'product_id' => $widget->id,
            'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs'],
            'quantity' => 1,
            'status' => ProductionOrderStatus::Pending,
        ]);
        $order->items()->create([
            'raw_material_id' => $steel->id,
            'raw_material_snapshot' => ['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg'],
            'quantity_per_unit' => 5, 'quantity_required' => 5,
        ]);
        valuationStock($warehouse, $steel, 100);

        $reports = app(StockReportService::class);
        expect($reports->productionPipeline()->blocked)->toBe(0);

        // The material is gone, so this build can provably never be completed.
        $steel->delete();
        expect($reports->productionPipeline()->blocked)->toBe(1);
    });
});

it('totals the purchase orders still on their way, flagging the late ones', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);

        $onTime = PurchaseOrder::create([
            'status' => PurchaseOrderStatus::Pending, 'currency' => 'MYR',
            'exchange_rate' => 1, 'expected_date' => now()->addWeek(),
        ]);
        $onTime->items()->create([
            'raw_material_id' => $steel->id,
            'raw_material_snapshot' => ['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg'],
            'quantity' => 10, 'unit_cost' => 5,
        ]);

        $late = PurchaseOrder::create([
            'status' => PurchaseOrderStatus::Pending, 'currency' => 'USD',
            'exchange_rate' => 4, 'expected_date' => now()->subDays(3),
        ]);
        $late->items()->create([
            'raw_material_id' => $steel->id,
            'raw_material_snapshot' => ['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg'],
            'quantity' => 10, 'unit_cost' => 2,
        ]);

        $incoming = app(StockReportService::class)->openPurchaseTotals();

        // 10×5×1 = 50, plus 10×2×4 = 80 → 130 in base currency.
        expect($incoming->count)->toBe(2)
            ->and($incoming->amount)->toBe(130.0)
            ->and($incoming->overdue)->toBe(1);
    });
});

it('flags a build as blocked when no single warehouse holds every material', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);
        $bolt = RawMaterial::create(['name' => 'Bolt', 'sku' => 'BO-1', 'unit' => 'pcs']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        $north = valuationWarehouse('North');
        $south = valuationWarehouse('South');

        $order = ProductionOrder::create([
            'product_id' => $widget->id,
            'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs'],
            'quantity' => 1,
            'status' => ProductionOrderStatus::Pending,
        ]);
        foreach ([[$steel, 5], [$bolt, 5]] as [$material, $needed]) {
            $order->items()->create([
                'raw_material_id' => $material->id,
                'raw_material_snapshot' => ['name' => $material->name, 'sku' => $material->sku, 'unit' => $material->unit],
                'quantity_per_unit' => $needed,
                'quantity_required' => $needed,
            ]);
        }

        // Both materials exist, but split across warehouses — the build can't run.
        valuationStock($north, $steel, 100);
        valuationStock($south, $bolt, 100);

        $pipeline = app(StockReportService::class)->productionPipeline();
        expect($pipeline->active)->toBe(1)->and($pipeline->blocked)->toBe(1);

        // Bring the bolts to the same warehouse and it's no longer blocked.
        valuationStock($north, $bolt, 100);
        expect(app(StockReportService::class)->productionPipeline()->blocked)->toBe(0);
    });
});

it('counts a sales order ready to ship only when one warehouse covers all of it', function () {
    $this->tenant->run(function () {
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        $gadget = Product::create(['name' => 'Gadget', 'sku' => 'G-1', 'unit' => 'pcs']);
        $north = valuationWarehouse('North');
        $south = valuationWarehouse('South');

        $order = SalesOrder::create([
            'customer_id' => null, 'status' => SalesOrderStatus::Pending, 'currency' => 'MYR',
        ]);
        foreach ([$widget, $gadget] as $product) {
            $order->items()->create([
                'product_id' => $product->id,
                'product_snapshot' => ['name' => $product->name, 'sku' => $product->sku, 'unit' => 'pcs'],
                'quantity' => 3, 'unit_price' => 10,
            ]);
        }

        $reports = app(StockReportService::class);

        // Split across warehouses → the all-or-nothing Fulfil would fail.
        valuationStock($north, $widget, 10);
        valuationStock($south, $gadget, 10);
        expect($reports->salesOrdersReadyToShip())->toBe(0);

        // One warehouse can now cover the whole order.
        valuationStock($north, $gadget, 10);
        expect($reports->salesOrdersReadyToShip())->toBe(1);
    });
});
