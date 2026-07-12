<?php

use App\Actions\ProvisionTenant;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use App\Models\WarehouseReorderLevel;
use App\Services\StockService;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/**
 * Seed a site + warehouse + a product and a raw material inside the tenant DB.
 *
 * @return array{warehouse: int, product: int, raw_material: int}
 */
function seedOnHandFixture(): array
{
    return test()->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);
        $warehouse = Warehouse::create(['location_id' => $location->id, 'name' => 'Main']);
        $product = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        $rawMaterial = RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg']);

        return [
            'warehouse' => $warehouse->id,
            'product' => $product->id,
            'raw_material' => $rawMaterial->id,
        ];
    });
}

it('reports on-hand for a (warehouse, stockable) via the service, zero when none', function () {
    ['warehouse' => $warehouseId, 'product' => $productId] = seedOnHandFixture();

    $this->tenant->run(function () use ($warehouseId, $productId) {
        $service = app(StockService::class);
        $warehouse = Warehouse::find($warehouseId);
        $product = Product::find($productId);

        expect($service->onHand($warehouse, $product))->toBe(0.0);

        $service->record($warehouse, $product, 12.5, StockMovementReason::Adjustment);

        expect($service->onHand($warehouse, $product))->toBe(12.5);
    });
});

it('returns on-hand, unit and reorder level for a stocked product', function () {
    ['warehouse' => $warehouseId, 'product' => $productId] = seedOnHandFixture();

    $this->tenant->run(function () use ($warehouseId, $productId) {
        app(StockService::class)->record(
            Warehouse::find($warehouseId),
            Product::find($productId),
            8,
            StockMovementReason::Adjustment,
        );
        WarehouseReorderLevel::create([
            'warehouse_id' => $warehouseId,
            'stockable_type' => 'product',
            'stockable_id' => $productId,
            'min_stock' => 5,
        ]);
    });

    loginAsAcmeUser();

    $response = $this
        ->getJson("/acme/stock/on-hand?warehouse_id={$warehouseId}&stockable=product:{$productId}")
        ->assertOk();

    expect((float) $response->json('on_hand'))->toBe(8.0)
        ->and($response->json('unit'))->toBe('pcs')
        ->and((float) $response->json('reorder_level'))->toBe(5.0);
});

it('returns zero on-hand and null reorder level when nothing is set', function () {
    ['warehouse' => $warehouseId, 'product' => $productId] = seedOnHandFixture();

    loginAsAcmeUser();

    $response = $this
        ->getJson("/acme/stock/on-hand?warehouse_id={$warehouseId}&stockable=product:{$productId}")
        ->assertOk();

    expect((float) $response->json('on_hand'))->toBe(0.0)
        ->and($response->json('unit'))->toBe('pcs')
        ->and($response->json('reorder_level'))->toBeNull();
});

it('resolves raw materials too', function () {
    ['warehouse' => $warehouseId, 'raw_material' => $rawMaterialId] = seedOnHandFixture();

    $this->tenant->run(function () use ($warehouseId, $rawMaterialId) {
        app(StockService::class)->record(
            Warehouse::find($warehouseId),
            RawMaterial::find($rawMaterialId),
            3,
            StockMovementReason::Adjustment,
        );
    });

    loginAsAcmeUser();

    $response = $this
        ->getJson("/acme/stock/on-hand?warehouse_id={$warehouseId}&stockable=raw_material:{$rawMaterialId}")
        ->assertOk();

    expect((float) $response->json('on_hand'))->toBe(3.0)
        ->and($response->json('unit'))->toBe('kg');
});

it('validates the warehouse and stockable', function () {
    ['warehouse' => $warehouseId, 'product' => $productId] = seedOnHandFixture();

    loginAsAcmeUser();

    // Malformed picker value.
    $this->getJson("/acme/stock/on-hand?warehouse_id={$warehouseId}&stockable=nope")
        ->assertStatus(422);

    // Nonexistent warehouse.
    $this->getJson("/acme/stock/on-hand?warehouse_id=99999&stockable=product:{$productId}")
        ->assertStatus(422);

    // Well-formed but nonexistent item.
    $this->getJson("/acme/stock/on-hand?warehouse_id={$warehouseId}&stockable=product:99999")
        ->assertStatus(422);
});

it('redirects a guest to the tenant login', function () {
    $this->get('/acme/stock/on-hand?warehouse_id=1&stockable=product:1')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});
