<?php

use App\Actions\ProvisionTenant;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\PurchaseReturn;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\StockService;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/** Seed a warehouse holding 20kg of a raw material + a supplier. @return array{warehouse:int, raw_material:int, supplier:int} */
function seedPurchaseReturnFixture(): array
{
    return test()->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);
        $warehouse = Warehouse::create(['location_id' => $location->id, 'name' => 'Main']);
        $rawMaterial = RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg']);
        $supplier = Supplier::create(['name' => 'Acme Supplies']);

        app(StockService::class)->record(
            Warehouse::find($warehouse->id),
            RawMaterial::find($rawMaterial->id),
            20,
            StockMovementReason::Adjustment,
        );

        return [
            'warehouse' => $warehouse->id,
            'raw_material' => $rawMaterial->id,
            'supplier' => $supplier->id,
        ];
    });
}

it('redirects a guest from the purchase returns page to the tenant login', function () {
    $this->get('/acme/purchase-returns')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('creates a purchase return and completes it, posting stock OUT', function () {
    ['warehouse' => $wh, 'raw_material' => $rm, 'supplier' => $sup] = seedPurchaseReturnFixture();
    loginAsAcmeUser();

    $this->post('/acme/purchase-returns', [
        'supplier_id' => $sup,
        'items' => [['raw_material_id' => $rm, 'quantity' => 5]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $returnId = $this->tenant->run(fn () => PurchaseReturn::first()->id);

    $this->post("/acme/purchase-returns/{$returnId}/complete", ['warehouse_id' => $wh])
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->tenant->run(function () use ($wh, $rm, $returnId) {
        expect(PurchaseReturn::find($returnId)->status->value)->toBe('completed');

        $stock = WarehouseStock::where('warehouse_id', $wh)
            ->where('stockable_type', 'raw_material')
            ->where('stockable_id', $rm)
            ->first();
        expect((float) $stock->quantity)->toBe(15.0);

        $movement = StockMovement::where('reason', 'purchase_return')->first();
        expect($movement)->not->toBeNull()
            ->and((float) $movement->quantity)->toBe(-5.0);
    });
});

it('cannot return more raw material than is on hand', function () {
    ['warehouse' => $wh, 'raw_material' => $rm] = seedPurchaseReturnFixture();
    loginAsAcmeUser();

    $this->post('/acme/purchase-returns', [
        'items' => [['raw_material_id' => $rm, 'quantity' => 50]],
    ]);
    $returnId = $this->tenant->run(fn () => PurchaseReturn::first()->id);

    $this->from('/acme/purchase-returns')
        ->post("/acme/purchase-returns/{$returnId}/complete", ['warehouse_id' => $wh])
        ->assertRedirect()
        ->assertSessionHasErrors('warehouse_id');

    $this->tenant->run(fn () => expect(PurchaseReturn::find($returnId)->status->value)->toBe('pending'));
});
