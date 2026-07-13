<?php

use App\Actions\ProvisionTenant;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/**
 * A product with a two-line BOM (2× Steel + 4× Bolt per unit) and a warehouse.
 *
 * @return array{widget: int, steel: int, bolt: int, warehouse: int}
 */
function seedProductionFixture(): array
{
    return test()->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg']);
        $bolt = RawMaterial::create(['name' => 'Bolt', 'sku' => 'B-1', 'unit' => 'ea']);
        $widget->bomItems()->create(['raw_material_id' => $steel->id, 'quantity' => 2]);
        $widget->bomItems()->create(['raw_material_id' => $bolt->id, 'quantity' => 4]);

        return [
            'widget' => $widget->id,
            'steel' => $steel->id,
            'bolt' => $bolt->id,
            'warehouse' => Warehouse::create(['location_id' => $location->id, 'name' => 'Main'])->id,
        ];
    });
}

/** Create a pending production order for $qty of $widgetId, exploding its BOM. */
function makePendingMo(int $widgetId, float $qty = 3): int
{
    return test()->tenant->run(function () use ($widgetId, $qty) {
        $product = Product::with('bomItems.rawMaterial')->find($widgetId);
        $order = ProductionOrder::create([
            'product_id' => $product->id,
            'product_snapshot' => ['name' => $product->name, 'sku' => $product->sku, 'unit' => $product->unit],
            'quantity' => $qty,
            'status' => 'pending',
        ]);
        foreach ($product->bomItems as $item) {
            $order->items()->create([
                'raw_material_id' => $item->raw_material_id,
                'raw_material_snapshot' => [
                    'name' => $item->rawMaterial->name,
                    'sku' => $item->rawMaterial->sku,
                    'unit' => $item->rawMaterial->unit,
                ],
                'quantity_per_unit' => $item->quantity,
                'quantity_required' => (float) $item->quantity * $qty,
            ]);
        }

        return $order->id;
    });
}

/** Put $qty of a raw material on-hand at $warehouseId via the service. */
function seedRawOnHand(int $warehouseId, int $rawMaterialId, float $qty): void
{
    test()->tenant->run(function () use ($warehouseId, $rawMaterialId, $qty) {
        app(StockService::class)->record(
            Warehouse::find($warehouseId),
            RawMaterial::find($rawMaterialId),
            $qty,
            StockMovementReason::Adjustment,
        );
    });
}

it('redirects a guest from the production orders page to the tenant login', function () {
    $this->get('/acme/production-orders')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('shows a printable work order document', function () {
    ['widget' => $widget] = seedProductionFixture();
    $moId = makePendingMo($widget, 3);

    loginAsAcmeUser();

    $this->get("/acme/production-orders/{$moId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/production-orders/show')
            ->where('order.id', $moId)
            ->has('order.items', 2)
        );
});

it('creates a production order, exploding the BOM into snapshotted items', function () {
    ['widget' => $widget, 'steel' => $steel, 'bolt' => $bolt] = seedProductionFixture();

    loginAsAcmeUser();

    $this->from('/acme/production-orders')
        ->post('/acme/production-orders', [
            'product_id' => $widget,
            'quantity' => 3,
        ])
        ->assertRedirect('/acme/production-orders')
        ->assertToast('Production order created.');

    $this->tenant->run(function () use ($steel, $bolt) {
        $order = ProductionOrder::with('items')->first();
        expect($order->status->value)->toBe('pending')
            ->and($order->product_snapshot['name'])->toBe('Widget')
            ->and((float) $order->quantity)->toBe(3.0)
            ->and($order->items)->toHaveCount(2);

        // quantity_required = per-unit BOM quantity × order quantity.
        $steelLine = $order->items->firstWhere('raw_material_id', $steel);
        $boltLine = $order->items->firstWhere('raw_material_id', $bolt);
        expect((float) $steelLine->quantity_required)->toBe(6.0)  // 2 × 3
            ->and((float) $boltLine->quantity_required)->toBe(12.0) // 4 × 3
            ->and($steelLine->raw_material_snapshot['name'])->toBe('Steel');
    });
});

it('rejects creating a production order for a product with no BOM', function () {
    ['warehouse' => $warehouse] = seedProductionFixture();

    $plain = test()->tenant->run(
        fn () => Product::create(['name' => 'Plain', 'sku' => 'P-1', 'unit' => 'ea'])->id,
    );

    loginAsAcmeUser();

    $this->from('/acme/production-orders')
        ->post('/acme/production-orders', [
            'product_id' => $plain,
            'quantity' => 1,
        ])
        ->assertRedirect('/acme/production-orders')
        ->assertInvalid('product_id');

    $this->tenant->run(fn () => expect(ProductionOrder::count())->toBe(0));
});

it('completes an order: consumes materials OUT and outputs the product IN', function () {
    ['widget' => $widget, 'steel' => $steel, 'bolt' => $bolt, 'warehouse' => $warehouse] = seedProductionFixture();
    seedRawOnHand($warehouse, $steel, 10);
    seedRawOnHand($warehouse, $bolt, 20);
    $moId = makePendingMo($widget, 3); // needs 6 steel + 12 bolt, makes 3 widgets

    loginAsAcmeUser();

    $this->from('/acme/production-orders')
        ->post("/acme/production-orders/{$moId}/complete", ['warehouse_id' => $warehouse])
        ->assertRedirect('/acme/production-orders')
        ->assertToast('Production order completed.');

    $this->tenant->run(function () use ($moId, $widget, $steel, $bolt, $warehouse) {
        $order = ProductionOrder::find($moId);
        expect($order->status->value)->toBe('completed')
            ->and($order->completed_at)->not->toBeNull()
            ->and($order->completed_warehouse_id)->toBe($warehouse);

        $steelStock = WarehouseStock::where('warehouse_id', $warehouse)
            ->where('stockable_type', 'raw_material')->where('stockable_id', $steel)->first();
        $boltStock = WarehouseStock::where('warehouse_id', $warehouse)
            ->where('stockable_type', 'raw_material')->where('stockable_id', $bolt)->first();
        $widgetStock = WarehouseStock::where('warehouse_id', $warehouse)
            ->where('stockable_type', 'product')->where('stockable_id', $widget)->first();

        expect((float) $steelStock->quantity)->toBe(4.0)   // 10 − 6
            ->and((float) $boltStock->quantity)->toBe(8.0)  // 20 − 12
            ->and((float) $widgetStock->quantity)->toBe(3.0) // 0 + 3
            ->and(StockMovement::where('reason', 'production_consume')->count())->toBe(2)
            ->and(StockMovement::where('reason', 'production_output')->count())->toBe(1);
    });
});

it('rolls back the whole completion when a material is short', function () {
    ['widget' => $widget, 'steel' => $steel, 'bolt' => $bolt, 'warehouse' => $warehouse] = seedProductionFixture();
    seedRawOnHand($warehouse, $steel, 10); // enough
    seedRawOnHand($warehouse, $bolt, 5);   // short: needs 12
    $moId = makePendingMo($widget, 3);

    loginAsAcmeUser();

    $this->from('/acme/production-orders')
        ->post("/acme/production-orders/{$moId}/complete", ['warehouse_id' => $warehouse])
        ->assertRedirect('/acme/production-orders')
        ->assertInvalid('warehouse_id');

    $this->tenant->run(function () use ($moId, $widget, $steel, $warehouse) {
        // Steel was consumed first but the bolt shortage rolls the whole thing back.
        $steelStock = WarehouseStock::where('warehouse_id', $warehouse)
            ->where('stockable_type', 'raw_material')->where('stockable_id', $steel)->first();
        $widgetStock = WarehouseStock::where('warehouse_id', $warehouse)
            ->where('stockable_type', 'product')->where('stockable_id', $widget)->first();

        expect((float) $steelStock->quantity)->toBe(10.0) // untouched
            ->and($widgetStock)->toBeNull()               // nothing produced
            ->and(ProductionOrder::find($moId)->status->value)->toBe('pending')
            ->and(StockMovement::where('reason', 'production_consume')->count())->toBe(0)
            ->and(StockMovement::where('reason', 'production_output')->count())->toBe(0);
    });
});

it('cancels a pending order and then refuses to complete it', function () {
    ['widget' => $widget, 'steel' => $steel, 'bolt' => $bolt, 'warehouse' => $warehouse] = seedProductionFixture();
    seedRawOnHand($warehouse, $steel, 10);
    seedRawOnHand($warehouse, $bolt, 20);
    $moId = makePendingMo($widget, 3);

    loginAsAcmeUser();

    $this->from('/acme/production-orders')
        ->post("/acme/production-orders/{$moId}/cancel")
        ->assertRedirect('/acme/production-orders')
        ->assertToast('Production order cancelled.');

    $this->tenant->run(fn () => expect(ProductionOrder::find($moId)->status->value)->toBe('cancelled'));

    $this->post("/acme/production-orders/{$moId}/complete", ['warehouse_id' => $warehouse])
        ->assertStatus(422);
});

it('lists production orders with picker options', function () {
    ['widget' => $widget] = seedProductionFixture();
    makePendingMo($widget, 3);

    loginAsAcmeUser();

    $this->get('/acme/production-orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/production-orders/index')
            ->has('orders.data', 1)
            ->has('orders.data.0.items', 2)
            ->has('products', 1)
            ->has('productBoms')
            ->has('warehouses', 1)
        );
});

it('filters, paginates and orders the production orders index by query params', function () {
    ['widget' => $widget, 'steel' => $steel] = seedProductionFixture();

    $gizmo = $this->tenant->run(function () use ($steel) {
        $product = Product::create(['name' => 'Gizmo', 'sku' => 'G-1', 'unit' => 'ea']);
        $product->bomItems()->create(['raw_material_id' => $steel, 'quantity' => 1]);

        return $product->id;
    });

    $id1 = makePendingMo($widget, 3);
    $id2 = makePendingMo($gizmo, 2);
    $id3 = makePendingMo($widget, 1);

    loginAsAcmeUser();

    // ?search matches the snapshotted product name.
    $this->get('/acme/production-orders?search=Gizmo')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $id2)
            ->where('filters.search', 'Gizmo'));

    $this->get('/acme/production-orders?search=Zenith')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 0)
            ->where('filters.search', 'Zenith'));

    $this->get('/acme/production-orders?per_page=25')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 3)
            ->where('orders.per_page', 25));

    $this->get('/acme/production-orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.id', $id3)
            ->where('orders.data.2.id', $id1));
});

it('deletes a production order', function () {
    ['widget' => $widget] = seedProductionFixture();
    $moId = makePendingMo($widget, 3);

    loginAsAcmeUser();

    $this->from('/acme/production-orders')
        ->delete("/acme/production-orders/{$moId}")
        ->assertRedirect('/acme/production-orders')
        ->assertToast('Production order deleted.');

    $this->tenant->run(fn () => expect(ProductionOrder::find($moId))->toBeNull());
});
