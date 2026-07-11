<?php

use App\Actions\ProvisionTenant;
use App\Models\Location;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/**
 * Seed a site + warehouse (the stock holder) + a product inside the tenant DB
 * and return the ids.
 *
 * @return array{warehouse: int, product: int}
 */
function seedStockFixture(): array
{
    return test()->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);
        $warehouse = Warehouse::create(['location_id' => $location->id, 'name' => 'Main']);
        $product = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        return ['warehouse' => $warehouse->id, 'product' => $product->id];
    });
}

it('redirects a guest from the stock movements page to the tenant login', function () {
    $this->get('/acme/stock-movements')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('records an IN movement, increasing on-hand and writing a ledger row', function () {
    ['warehouse' => $warehouseId, 'product' => $productId] = seedStockFixture();

    loginAsAcmeUser();

    $this->from('/acme/stock-movements')
        ->post('/acme/stock-movements', [
            'warehouse_id' => $warehouseId,
            'stockable' => "product:{$productId}",
            'type' => 'in',
            'quantity' => 10,
        ])
        ->assertRedirect('/acme/stock-movements')
        ->assertSessionHasNoErrors()
        ->assertToast('Movement recorded.');

    $this->tenant->run(function () use ($warehouseId, $productId) {
        $stock = WarehouseStock::where('warehouse_id', $warehouseId)
            ->where('stockable_type', 'product')
            ->where('stockable_id', $productId)
            ->first();

        expect((float) $stock->quantity)->toBe(10.0);

        $movement = StockMovement::first();
        expect((float) $movement->quantity)->toBe(10.0)
            ->and($movement->reason->value)->toBe('adjustment')
            ->and($movement->stockable_type)->toBe('product');
    });
});

it('records an OUT movement, decreasing on-hand', function () {
    ['warehouse' => $warehouseId, 'product' => $productId] = seedStockFixture();

    loginAsAcmeUser();

    // Seed 10 in first.
    $this->post('/acme/stock-movements', [
        'warehouse_id' => $warehouseId,
        'stockable' => "product:{$productId}",
        'type' => 'in',
        'quantity' => 10,
    ]);

    $this->from('/acme/stock-movements')
        ->post('/acme/stock-movements', [
            'warehouse_id' => $warehouseId,
            'stockable' => "product:{$productId}",
            'type' => 'out',
            'quantity' => 3,
        ])
        ->assertRedirect('/acme/stock-movements')
        ->assertSessionHasNoErrors();

    $this->tenant->run(function () use ($warehouseId, $productId) {
        $stock = WarehouseStock::where('warehouse_id', $warehouseId)
            ->where('stockable_id', $productId)
            ->first();

        expect((float) $stock->quantity)->toBe(7.0)
            ->and(StockMovement::count())->toBe(2);
    });
});

it('rejects an OUT movement that would drive on-hand negative', function () {
    ['warehouse' => $warehouseId, 'product' => $productId] = seedStockFixture();

    loginAsAcmeUser();

    $this->post('/acme/stock-movements', [
        'warehouse_id' => $warehouseId,
        'stockable' => "product:{$productId}",
        'type' => 'in',
        'quantity' => 10,
    ]);

    $this->from('/acme/stock-movements')
        ->post('/acme/stock-movements', [
            'warehouse_id' => $warehouseId,
            'stockable' => "product:{$productId}",
            'type' => 'out',
            'quantity' => 100,
        ])
        ->assertRedirect('/acme/stock-movements')
        ->assertInvalid('quantity');

    $this->tenant->run(function () use ($warehouseId, $productId) {
        $stock = WarehouseStock::where('warehouse_id', $warehouseId)
            ->where('stockable_id', $productId)
            ->first();

        // On-hand unchanged and no ledger row beyond the initial IN.
        expect((float) $stock->quantity)->toBe(10.0)
            ->and(StockMovement::count())->toBe(1);
    });
});

it('adjusts on-hand to an absolute target, recording the signed delta', function () {
    ['warehouse' => $warehouseId, 'product' => $productId] = seedStockFixture();

    loginAsAcmeUser();

    // Bring on-hand to 10 first.
    $this->post('/acme/stock-movements', [
        'warehouse_id' => $warehouseId,
        'stockable' => "product:{$productId}",
        'type' => 'in',
        'quantity' => 10,
    ]);

    $this->from('/acme/stock-movements')
        ->post('/acme/stock-movements', [
            'warehouse_id' => $warehouseId,
            'stockable' => "product:{$productId}",
            'type' => 'adjustment',
            'quantity' => 8,
        ])
        ->assertRedirect('/acme/stock-movements')
        ->assertSessionHasNoErrors();

    $this->tenant->run(function () use ($warehouseId, $productId) {
        $stock = WarehouseStock::where('warehouse_id', $warehouseId)
            ->where('stockable_id', $productId)
            ->first();

        expect((float) $stock->quantity)->toBe(8.0);

        $adjustment = StockMovement::where('reason', 'adjustment')->latest('id')->first();
        expect((float) $adjustment->quantity)->toBe(-2.0);
    });
});

it('records a movement for a raw material, storing the raw_material morph type', function () {
    $warehouseId = $this->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);

        return Warehouse::create(['location_id' => $location->id, 'name' => 'Main'])->id;
    });

    $rawMaterialId = $this->tenant->run(
        fn () => RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg'])->id,
    );

    loginAsAcmeUser();

    $this->from('/acme/stock-movements')
        ->post('/acme/stock-movements', [
            'warehouse_id' => $warehouseId,
            'stockable' => "raw_material:{$rawMaterialId}",
            'type' => 'in',
            'quantity' => 25,
        ])
        ->assertRedirect('/acme/stock-movements')
        ->assertSessionHasNoErrors();

    $this->tenant->run(function () use ($warehouseId, $rawMaterialId) {
        $stock = WarehouseStock::where('warehouse_id', $warehouseId)
            ->where('stockable_type', 'raw_material')
            ->where('stockable_id', $rawMaterialId)
            ->first();

        expect($stock)->not->toBeNull()
            ->and((float) $stock->quantity)->toBe(25.0)
            ->and(StockMovement::where('stockable_type', 'raw_material')->exists())->toBeTrue();
    });
});

it('rejects a malformed stockable value', function () {
    $warehouseId = $this->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);

        return Warehouse::create(['location_id' => $location->id, 'name' => 'Main'])->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/stock-movements')
        ->post('/acme/stock-movements', [
            'warehouse_id' => $warehouseId,
            'stockable' => 'banana:1',
            'type' => 'in',
            'quantity' => 5,
        ])
        ->assertRedirect('/acme/stock-movements')
        ->assertInvalid('stockable');
});

it('rejects a stockable that does not exist', function () {
    $warehouseId = $this->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);

        return Warehouse::create(['location_id' => $location->id, 'name' => 'Main'])->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/stock-movements')
        ->post('/acme/stock-movements', [
            'warehouse_id' => $warehouseId,
            'stockable' => 'product:999999',
            'type' => 'in',
            'quantity' => 5,
        ])
        ->assertRedirect('/acme/stock-movements')
        ->assertInvalid('stockable');
});

it('lists the ledger with picker options', function () {
    ['warehouse' => $warehouseId, 'product' => $productId] = seedStockFixture();

    loginAsAcmeUser();

    $this->post('/acme/stock-movements', [
        'warehouse_id' => $warehouseId,
        'stockable' => "product:{$productId}",
        'type' => 'in',
        'quantity' => 10,
    ]);

    $this->get('/acme/stock-movements')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/stock-movements/index')
            ->has('movements.data', 1)
            ->has('warehouses', 1)
            ->has('items', 1)
            ->where('items.0.value', "product:{$productId}")
        );
});

it('filters the movement ledger by item name and by warehouse', function () {
    test()->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);
        $warehouse = Warehouse::create(['location_id' => $location->id, 'name' => 'Main']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);
        $gadget = Product::create(['name' => 'Gadget', 'sku' => 'G-1', 'unit' => 'ea']);

        foreach ([[$widget->id, 5], [$gadget->id, 7]] as [$id, $qty]) {
            StockMovement::create([
                'warehouse_id' => $warehouse->id,
                'stockable_type' => 'product',
                'stockable_id' => $id,
                'quantity' => $qty,
                'reason' => 'adjustment',
            ]);
        }
    });

    loginAsAcmeUser();

    // Item-name match runs across the polymorphic stockable.
    $this->get('/acme/stock-movements?search=Widget')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/stock-movements/index')
            ->has('movements.data', 1)
            ->where('movements.data.0.item', 'Widget · Product')
            ->where('filters.search', 'Widget')
        );

    // The site name matches every row at that warehouse.
    $this->get('/acme/stock-movements?search=KL')
        ->assertInertia(fn (Assert $page) => $page->has('movements.data', 2));

    // A term that matches nothing returns an empty ledger.
    $this->get('/acme/stock-movements?search=Nope')
        ->assertInertia(fn (Assert $page) => $page->has('movements.data', 0));
});
