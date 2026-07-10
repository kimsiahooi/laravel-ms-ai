<?php

use App\Actions\ProvisionTenant;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\LocationStock;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/**
 * A product with a two-line BOM (2× Steel + 4× Bolt per unit) and a location.
 *
 * @return array{widget: int, steel: int, bolt: int, location: int}
 */
function seedProductionFixture(): array
{
    return test()->tenant->run(function () {
        $warehouse = Warehouse::create(['name' => 'Main']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea', 'min_stock' => 0]);
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg', 'min_stock' => 0]);
        $bolt = RawMaterial::create(['name' => 'Bolt', 'sku' => 'B-1', 'unit' => 'ea', 'min_stock' => 0]);
        $widget->bomItems()->create(['raw_material_id' => $steel->id, 'quantity' => 2]);
        $widget->bomItems()->create(['raw_material_id' => $bolt->id, 'quantity' => 4]);

        return [
            'widget' => $widget->id,
            'steel' => $steel->id,
            'bolt' => $bolt->id,
            'location' => Location::create(['warehouse_id' => $warehouse->id, 'code' => 'A-01'])->id,
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
        foreach ($product->bomItems as $bom) {
            $order->items()->create([
                'raw_material_id' => $bom->raw_material_id,
                'raw_material_snapshot' => [
                    'name' => $bom->rawMaterial->name,
                    'sku' => $bom->rawMaterial->sku,
                    'unit' => $bom->rawMaterial->unit,
                ],
                'quantity_per_unit' => $bom->quantity,
                'quantity_required' => (float) $bom->quantity * $qty,
            ]);
        }

        return $order->id;
    });
}

/** Put $qty of a raw material on-hand at $locationId via the service. */
function seedRawOnHand(int $locationId, int $rawMaterialId, float $qty): void
{
    test()->tenant->run(function () use ($locationId, $rawMaterialId, $qty) {
        app(StockService::class)->record(
            Location::find($locationId),
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
    ['location' => $location] = seedProductionFixture();

    $plain = test()->tenant->run(
        fn () => Product::create(['name' => 'Plain', 'sku' => 'P-1', 'unit' => 'ea', 'min_stock' => 0])->id,
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
    ['widget' => $widget, 'steel' => $steel, 'bolt' => $bolt, 'location' => $location] = seedProductionFixture();
    seedRawOnHand($location, $steel, 10);
    seedRawOnHand($location, $bolt, 20);
    $moId = makePendingMo($widget, 3); // needs 6 steel + 12 bolt, makes 3 widgets

    loginAsAcmeUser();

    $this->from('/acme/production-orders')
        ->post("/acme/production-orders/{$moId}/complete", ['location_id' => $location])
        ->assertRedirect('/acme/production-orders')
        ->assertToast('Production order completed.');

    $this->tenant->run(function () use ($moId, $widget, $steel, $bolt, $location) {
        $order = ProductionOrder::find($moId);
        expect($order->status->value)->toBe('completed')
            ->and($order->completed_at)->not->toBeNull()
            ->and($order->completed_location_id)->toBe($location);

        $steelStock = LocationStock::where('location_id', $location)
            ->where('stockable_type', 'raw_material')->where('stockable_id', $steel)->first();
        $boltStock = LocationStock::where('location_id', $location)
            ->where('stockable_type', 'raw_material')->where('stockable_id', $bolt)->first();
        $widgetStock = LocationStock::where('location_id', $location)
            ->where('stockable_type', 'product')->where('stockable_id', $widget)->first();

        expect((float) $steelStock->quantity)->toBe(4.0)   // 10 − 6
            ->and((float) $boltStock->quantity)->toBe(8.0)  // 20 − 12
            ->and((float) $widgetStock->quantity)->toBe(3.0) // 0 + 3
            ->and(StockMovement::where('reason', 'production_consume')->count())->toBe(2)
            ->and(StockMovement::where('reason', 'production_output')->count())->toBe(1);
    });
});

it('rolls back the whole completion when a material is short', function () {
    ['widget' => $widget, 'steel' => $steel, 'bolt' => $bolt, 'location' => $location] = seedProductionFixture();
    seedRawOnHand($location, $steel, 10); // enough
    seedRawOnHand($location, $bolt, 5);   // short: needs 12
    $moId = makePendingMo($widget, 3);

    loginAsAcmeUser();

    $this->from('/acme/production-orders')
        ->post("/acme/production-orders/{$moId}/complete", ['location_id' => $location])
        ->assertRedirect('/acme/production-orders')
        ->assertInvalid('location_id');

    $this->tenant->run(function () use ($moId, $widget, $steel, $location) {
        // Steel was consumed first but the bolt shortage rolls the whole thing back.
        $steelStock = LocationStock::where('location_id', $location)
            ->where('stockable_type', 'raw_material')->where('stockable_id', $steel)->first();
        $widgetStock = LocationStock::where('location_id', $location)
            ->where('stockable_type', 'product')->where('stockable_id', $widget)->first();

        expect((float) $steelStock->quantity)->toBe(10.0) // untouched
            ->and($widgetStock)->toBeNull()               // nothing produced
            ->and(ProductionOrder::find($moId)->status->value)->toBe('pending')
            ->and(StockMovement::where('reason', 'production_consume')->count())->toBe(0)
            ->and(StockMovement::where('reason', 'production_output')->count())->toBe(0);
    });
});

it('cancels a pending order and then refuses to complete it', function () {
    ['widget' => $widget, 'steel' => $steel, 'bolt' => $bolt, 'location' => $location] = seedProductionFixture();
    seedRawOnHand($location, $steel, 10);
    seedRawOnHand($location, $bolt, 20);
    $moId = makePendingMo($widget, 3);

    loginAsAcmeUser();

    $this->from('/acme/production-orders')
        ->post("/acme/production-orders/{$moId}/cancel")
        ->assertRedirect('/acme/production-orders')
        ->assertToast('Production order cancelled.');

    $this->tenant->run(fn () => expect(ProductionOrder::find($moId)->status->value)->toBe('cancelled'));

    $this->post("/acme/production-orders/{$moId}/complete", ['location_id' => $location])
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
            ->has('locations', 1)
        );
});
