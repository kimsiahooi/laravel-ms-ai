<?php

use App\Actions\ProvisionTenant;
use App\Models\Location;
use App\Models\LocationStock;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/**
 * Supplier + two raw materials + a location, in the tenant DB.
 *
 * @return array{supplier: int, steel: int, bolt: int, location: int}
 */
function seedPurchaseFixture(): array
{
    return test()->tenant->run(function () {
        $warehouse = Warehouse::create(['name' => 'Main']);

        return [
            'supplier' => Supplier::create(['name' => 'Acme Metals'])->id,
            'steel' => RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg', 'min_stock' => 0])->id,
            'bolt' => RawMaterial::create(['name' => 'Bolt', 'sku' => 'B-1', 'unit' => 'ea', 'min_stock' => 0])->id,
            'location' => Location::create(['warehouse_id' => $warehouse->id, 'code' => 'A-01'])->id,
        ];
    });
}

/** Create a pending PO with one steel line (qty 10). Returns its id. */
function makePendingPo(int $supplierId, int $steelId): int
{
    return test()->tenant->run(function () use ($supplierId, $steelId) {
        $order = PurchaseOrder::create([
            'supplier_id' => $supplierId,
            'currency' => 'USD',
            'status' => 'pending',
        ]);
        $order->items()->create([
            'raw_material_id' => $steelId,
            'raw_material_snapshot' => ['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg'],
            'quantity' => 10,
            'unit_cost' => 2,
        ]);

        return $order->id;
    });
}

it('redirects a guest from the purchase orders page to the tenant login', function () {
    $this->get('/acme/purchase-orders')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('shows a printable purchase order document', function () {
    ['supplier' => $supplier, 'steel' => $steel] = seedPurchaseFixture();
    $poId = makePendingPo($supplier, $steel);

    loginAsAcmeUser();

    $this->get("/acme/purchase-orders/{$poId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/purchase-orders/show')
            ->where('order.id', $poId)
            ->has('order.items', 1)
        );
});

it('creates a purchase order with line items and snapshots', function () {
    ['supplier' => $supplier, 'steel' => $steel, 'bolt' => $bolt] = seedPurchaseFixture();

    loginAsAcmeUser();

    $this->from('/acme/purchase-orders')
        ->post('/acme/purchase-orders', [
            'supplier_id' => $supplier,
            'currency' => 'USD',
            'items' => [
                ['raw_material_id' => $steel, 'quantity' => 10, 'unit_cost' => 2.5],
                ['raw_material_id' => $bolt, 'quantity' => 100, 'unit_cost' => 0.1],
            ],
        ])
        ->assertRedirect('/acme/purchase-orders')
        ->assertToast('Purchase order created.');

    $this->tenant->run(function () {
        $order = PurchaseOrder::with('items')->first();
        expect($order->status->value)->toBe('pending')
            ->and($order->items)->toHaveCount(2)
            ->and($order->items->first()->raw_material_snapshot['name'])->toBe('Steel');
    });
});

it('receives a pending PO: posts purchase_receipt IN per line and marks it received', function () {
    ['supplier' => $supplier, 'steel' => $steel, 'location' => $location] = seedPurchaseFixture();
    $poId = makePendingPo($supplier, $steel);

    loginAsAcmeUser();

    $this->from('/acme/purchase-orders')
        ->post("/acme/purchase-orders/{$poId}/receive", ['location_id' => $location])
        ->assertRedirect('/acme/purchase-orders')
        ->assertToast('Purchase order received.');

    $this->tenant->run(function () use ($poId, $steel, $location) {
        $order = PurchaseOrder::find($poId);
        expect($order->status->value)->toBe('received')
            ->and($order->received_at)->not->toBeNull()
            ->and($order->received_location_id)->toBe($location);

        $stock = LocationStock::where('location_id', $location)->where('stockable_id', $steel)->first();
        expect((float) $stock->quantity)->toBe(10.0)
            ->and(StockMovement::where('reason', 'purchase_receipt')->count())->toBe(1);
    });
});

it('rejects receiving a PO that is not pending', function () {
    ['supplier' => $supplier, 'steel' => $steel, 'location' => $location] = seedPurchaseFixture();
    $poId = makePendingPo($supplier, $steel);

    loginAsAcmeUser();

    $this->post("/acme/purchase-orders/{$poId}/receive", ['location_id' => $location]);

    // Second receive on the now-received PO is rejected.
    $this->post("/acme/purchase-orders/{$poId}/receive", ['location_id' => $location])
        ->assertStatus(422);

    $this->tenant->run(function () {
        expect(StockMovement::where('reason', 'purchase_receipt')->count())->toBe(1);
    });
});

it('cancels a pending PO but not a received one', function () {
    ['supplier' => $supplier, 'steel' => $steel, 'location' => $location] = seedPurchaseFixture();
    $poId = makePendingPo($supplier, $steel);

    loginAsAcmeUser();

    $this->from('/acme/purchase-orders')
        ->post("/acme/purchase-orders/{$poId}/cancel")
        ->assertRedirect('/acme/purchase-orders')
        ->assertToast('Purchase order cancelled.');

    $this->tenant->run(fn () => expect(PurchaseOrder::find($poId)->status->value)->toBe('cancelled'));

    // A cancelled PO can't be received.
    $this->post("/acme/purchase-orders/{$poId}/receive", ['location_id' => $location])
        ->assertStatus(422);
});

it('updates a pending PO but rejects updating a received one', function () {
    ['supplier' => $supplier, 'steel' => $steel, 'bolt' => $bolt, 'location' => $location] = seedPurchaseFixture();
    $poId = makePendingPo($supplier, $steel);

    loginAsAcmeUser();

    // Replace the single steel line with a bolt line.
    $this->from('/acme/purchase-orders')
        ->put("/acme/purchase-orders/{$poId}", [
            'supplier_id' => $supplier,
            'currency' => 'USD',
            'items' => [['raw_material_id' => $bolt, 'quantity' => 5, 'unit_cost' => 1]],
        ])
        ->assertRedirect('/acme/purchase-orders')
        ->assertToast('Purchase order updated.');

    $this->tenant->run(function () use ($poId, $bolt) {
        $order = PurchaseOrder::with('items')->find($poId);
        expect($order->items)->toHaveCount(1)
            ->and($order->items->first()->raw_material_id)->toBe($bolt);
    });

    // Receive it, then updating is rejected.
    $this->post("/acme/purchase-orders/{$poId}/receive", ['location_id' => $location]);

    $this->put("/acme/purchase-orders/{$poId}", [
        'supplier_id' => $supplier,
        'currency' => 'USD',
        'items' => [['raw_material_id' => $bolt, 'quantity' => 1, 'unit_cost' => 1]],
    ])->assertStatus(422);
});

it('requires at least one line item', function () {
    ['supplier' => $supplier] = seedPurchaseFixture();

    loginAsAcmeUser();

    $this->from('/acme/purchase-orders')
        ->post('/acme/purchase-orders', [
            'supplier_id' => $supplier,
            'currency' => 'USD',
            'items' => [],
        ])
        ->assertRedirect('/acme/purchase-orders')
        ->assertInvalid('items');
});

it('lists purchase orders with picker options', function () {
    ['supplier' => $supplier, 'steel' => $steel] = seedPurchaseFixture();
    makePendingPo($supplier, $steel);

    loginAsAcmeUser();

    $this->get('/acme/purchase-orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/purchase-orders/index')
            ->has('orders.data', 1)
            ->has('suppliers', 1)
            ->has('rawMaterials', 2)
            ->has('locations', 1)
        );
});
