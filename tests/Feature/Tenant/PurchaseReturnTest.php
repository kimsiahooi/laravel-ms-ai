<?php

use App\Actions\ProvisionTenant;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Inertia\Testing\AssertableInertia as Assert;

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

/** Create a pending purchase return for $supplierId with one $rmId line. Returns its id. */
function makePendingPurchaseReturn(int $supplierId, int $rmId, float $qty = 3): int
{
    return test()->tenant->run(function () use ($supplierId, $rmId, $qty) {
        $return = PurchaseReturn::create(['supplier_id' => $supplierId, 'status' => 'pending']);
        $rm = RawMaterial::find($rmId);
        $return->items()->create([
            'raw_material_id' => $rm->id,
            'raw_material_snapshot' => ['name' => $rm->name, 'sku' => $rm->sku, 'unit' => $rm->unit],
            'quantity' => $qty,
        ]);

        return $return->id;
    });
}

/** Record a received purchase order for $rmId from $supplierId, so returning it is valid (R11). */
function receivePurchaseFrom(int $supplierId, int $rmId): void
{
    test()->tenant->run(function () use ($supplierId, $rmId): void {
        $po = PurchaseOrder::create(['supplier_id' => $supplierId, 'status' => 'received']);
        $rm = RawMaterial::find($rmId);
        $po->items()->create([
            'raw_material_id' => $rmId,
            'raw_material_snapshot' => ['name' => $rm->name, 'sku' => $rm->sku, 'unit' => $rm->unit],
            'quantity' => 100,
            'unit_cost' => 1,
        ]);
    });
}

it('redirects a guest from the purchase returns page to the tenant login', function () {
    $this->get('/acme/purchase-returns')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('filters, paginates and orders the purchase returns index by query params', function () {
    ['raw_material' => $rm] = seedPurchaseReturnFixture();

    [$alpha, $beta] = $this->tenant->run(fn () => [
        Supplier::create(['name' => 'Alpha Metals'])->id,
        Supplier::create(['name' => 'Beta Supplies'])->id,
    ]);
    $id1 = makePendingPurchaseReturn($alpha, $rm);
    $id2 = makePendingPurchaseReturn($beta, $rm);
    $id3 = makePendingPurchaseReturn($alpha, $rm);

    loginAsAcmeUser();

    $this->get('/acme/purchase-returns?search=Beta')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('returns.data', 1)
            ->where('returns.data.0.id', $id2)
            ->where('filters.search', 'Beta'));

    $this->get('/acme/purchase-returns?search=Zenith')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('returns.data', 0)
            ->where('filters.search', 'Zenith'));

    $this->get('/acme/purchase-returns?per_page=25')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('returns.data', 3)
            ->where('returns.per_page', 25));

    $this->get('/acme/purchase-returns')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('returns.data.0.id', $id3)
            ->where('returns.data.2.id', $id1));
});

it('shows a purchase return', function () {
    ['supplier' => $supplier, 'raw_material' => $rm] = seedPurchaseReturnFixture();
    $returnId = makePendingPurchaseReturn($supplier, $rm);

    loginAsAcmeUser();

    $this->get("/acme/purchase-returns/{$returnId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/purchase-returns/show')
            ->where('return.id', $returnId)
            ->has('return.items', 1)
            ->has('warehouses')
            ->where('print', false));
});

it('updates a pending purchase return but rejects updating a non-pending one', function () {
    ['supplier' => $supplier, 'raw_material' => $rm] = seedPurchaseReturnFixture();
    $returnId = makePendingPurchaseReturn($supplier, $rm, 3);

    loginAsAcmeUser();
    receivePurchaseFrom($supplier, $rm); // R11: the item must have been received from the supplier

    $this->from('/acme/purchase-returns')
        ->put("/acme/purchase-returns/{$returnId}", [
            'supplier_id' => $supplier,
            'items' => [['raw_material_id' => $rm, 'quantity' => 8]],
        ])
        ->assertRedirect('/acme/purchase-returns')
        ->assertToast('Purchase return updated.');

    $this->tenant->run(fn () => expect((float) PurchaseReturn::with('items')->find($returnId)->items->first()->quantity)->toBe(8.0));

    // Cancel it, then updating is rejected.
    $this->post("/acme/purchase-returns/{$returnId}/cancel");
    $this->put("/acme/purchase-returns/{$returnId}", [
        'supplier_id' => $supplier,
        'items' => [['raw_material_id' => $rm, 'quantity' => 1]],
    ])->assertStatus(422);
});

it('cancels a pending purchase return but not again', function () {
    ['supplier' => $supplier, 'raw_material' => $rm] = seedPurchaseReturnFixture();
    $returnId = makePendingPurchaseReturn($supplier, $rm);

    loginAsAcmeUser();

    $this->from('/acme/purchase-returns')
        ->post("/acme/purchase-returns/{$returnId}/cancel")
        ->assertRedirect('/acme/purchase-returns')
        ->assertToast('Purchase return cancelled.');

    $this->tenant->run(fn () => expect(PurchaseReturn::find($returnId)->status->value)->toBe('cancelled'));

    $this->post("/acme/purchase-returns/{$returnId}/cancel")->assertStatus(422);
});

it('deletes a purchase return', function () {
    ['supplier' => $supplier, 'raw_material' => $rm] = seedPurchaseReturnFixture();
    $returnId = makePendingPurchaseReturn($supplier, $rm);

    loginAsAcmeUser();

    $this->from('/acme/purchase-returns')
        ->delete("/acme/purchase-returns/{$returnId}")
        ->assertRedirect('/acme/purchase-returns')
        ->assertToast('Purchase return deleted.');

    $this->tenant->run(fn () => expect(PurchaseReturn::find($returnId))->toBeNull());
});

it('rejects a purchase return for an item never received from the supplier', function () {
    ['supplier' => $sup, 'raw_material' => $rm] = seedPurchaseReturnFixture();
    loginAsAcmeUser();

    // No received purchase order for $rm from $sup → the item is rejected (R11).
    $this->from('/acme/purchase-returns')
        ->post('/acme/purchase-returns', [
            'supplier_id' => $sup,
            'items' => [['raw_material_id' => $rm, 'quantity' => 1]],
        ])
        ->assertRedirect('/acme/purchase-returns')
        ->assertSessionHasErrors('items.0.raw_material_id');

    $this->tenant->run(fn () => expect(PurchaseReturn::count())->toBe(0));
});

it('creates a purchase return and completes it, posting stock OUT', function () {
    ['warehouse' => $wh, 'raw_material' => $rm, 'supplier' => $sup] = seedPurchaseReturnFixture();
    loginAsAcmeUser();
    receivePurchaseFrom($sup, $rm); // R11: only items received from the supplier can be returned

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

it('takes no stock out at all when a later line is short', function () {
    ['warehouse' => $wh, 'raw_material' => $rm, 'supplier' => $sup] = seedPurchaseReturnFixture();
    loginAsAcmeUser();
    receivePurchaseFrom($sup, $rm);

    // A second material with only 1 in stock, so the *second* line fails after the
    // first has already been taken out.
    $scarce = $this->tenant->run(function () use ($wh, $sup) {
        $bolt = RawMaterial::create(['name' => 'Bolt', 'sku' => 'B-1', 'unit' => 'ea']);
        app(StockService::class)->record(
            Warehouse::find($wh), $bolt, 1, StockMovementReason::Adjustment,
        );

        $po = PurchaseOrder::create(['supplier_id' => $sup, 'status' => 'received']);
        $po->items()->create([
            'raw_material_id' => $bolt->id,
            'raw_material_snapshot' => ['name' => 'Bolt', 'sku' => 'B-1', 'unit' => 'ea'],
            'quantity' => 100, 'unit_cost' => 1,
        ]);

        return $bolt->id;
    });

    $this->post('/acme/purchase-returns', [
        'supplier_id' => $sup,
        'items' => [
            ['raw_material_id' => $rm, 'quantity' => 5],
            ['raw_material_id' => $scarce, 'quantity' => 50],
        ],
    ]);
    $returnId = $this->tenant->run(fn () => PurchaseReturn::first()->id);

    $this->from('/acme/purchase-returns')
        ->post("/acme/purchase-returns/{$returnId}/complete", ['warehouse_id' => $wh])
        ->assertSessionHasErrors('warehouse_id');

    $this->tenant->run(function () use ($returnId, $wh, $rm, $scarce) {
        expect(PurchaseReturn::find($returnId)->status->value)->toBe('pending')
            ->and(StockMovement::where('reason', 'purchase_return')->count())->toBe(0);

        // The first line's 5 were never taken out.
        foreach ([$rm => 20.0, $scarce => 1.0] as $materialId => $expected) {
            $stock = WarehouseStock::where('warehouse_id', $wh)
                ->where('stockable_type', 'raw_material')
                ->where('stockable_id', $materialId)
                ->first();
            expect((float) $stock->quantity)->toBe($expected);
        }
    });
});

it('cannot return more raw material than is on hand', function () {
    ['warehouse' => $wh, 'raw_material' => $rm, 'supplier' => $sup] = seedPurchaseReturnFixture();
    loginAsAcmeUser();
    receivePurchaseFrom($sup, $rm); // R11 precondition; creates no stock, so on-hand stays 20

    $this->post('/acme/purchase-returns', [
        'supplier_id' => $sup,
        'items' => [['raw_material_id' => $rm, 'quantity' => 50]],
    ]);
    $returnId = $this->tenant->run(fn () => PurchaseReturn::first()->id);

    $this->from('/acme/purchase-returns')
        ->post("/acme/purchase-returns/{$returnId}/complete", ['warehouse_id' => $wh])
        ->assertRedirect()
        ->assertSessionHasErrors('warehouse_id');

    $this->tenant->run(fn () => expect(PurchaseReturn::find($returnId)->status->value)->toBe('pending'));
});
