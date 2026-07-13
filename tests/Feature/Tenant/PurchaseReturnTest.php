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
            ->has('return.items', 1));
});

it('updates a pending purchase return but rejects updating a non-pending one', function () {
    ['supplier' => $supplier, 'raw_material' => $rm] = seedPurchaseReturnFixture();
    $returnId = makePendingPurchaseReturn($supplier, $rm, 3);

    loginAsAcmeUser();

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
