<?php

use App\Actions\ProvisionTenant;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/**
 * Supplier + two raw materials + a warehouse (stock holder), in the tenant DB.
 *
 * @return array{supplier: int, steel: int, bolt: int, warehouse: int}
 */
function seedPurchaseFixture(): array
{
    return test()->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);

        return [
            'supplier' => Supplier::create(['name' => 'Acme Metals'])->id,
            'steel' => RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg'])->id,
            'bolt' => RawMaterial::create(['name' => 'Bolt', 'sku' => 'B-1', 'unit' => 'ea'])->id,
            'warehouse' => Warehouse::create(['location_id' => $location->id, 'name' => 'Main'])->id,
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
    ['supplier' => $supplier, 'steel' => $steel, 'warehouse' => $warehouse] = seedPurchaseFixture();
    $poId = makePendingPo($supplier, $steel);

    loginAsAcmeUser();

    $this->from('/acme/purchase-orders')
        ->post("/acme/purchase-orders/{$poId}/receive", ['warehouse_id' => $warehouse])
        ->assertRedirect('/acme/purchase-orders')
        ->assertToast('Purchase order received.');

    $this->tenant->run(function () use ($poId, $steel, $warehouse) {
        $order = PurchaseOrder::find($poId);
        expect($order->status->value)->toBe('received')
            ->and($order->received_at)->not->toBeNull()
            ->and($order->received_warehouse_id)->toBe($warehouse);

        $stock = WarehouseStock::where('warehouse_id', $warehouse)->where('stockable_id', $steel)->first();
        expect((float) $stock->quantity)->toBe(10.0)
            ->and(StockMovement::where('reason', 'purchase_receipt')->count())->toBe(1);
    });
});

it('rejects receiving a PO that is not pending', function () {
    ['supplier' => $supplier, 'steel' => $steel, 'warehouse' => $warehouse] = seedPurchaseFixture();
    $poId = makePendingPo($supplier, $steel);

    loginAsAcmeUser();

    $this->post("/acme/purchase-orders/{$poId}/receive", ['warehouse_id' => $warehouse]);

    // Second receive on the now-received PO is rejected.
    $this->post("/acme/purchase-orders/{$poId}/receive", ['warehouse_id' => $warehouse])
        ->assertStatus(422);

    $this->tenant->run(function () {
        expect(StockMovement::where('reason', 'purchase_receipt')->count())->toBe(1);
    });
});

it('cancels a pending PO but not a received one', function () {
    ['supplier' => $supplier, 'steel' => $steel, 'warehouse' => $warehouse] = seedPurchaseFixture();
    $poId = makePendingPo($supplier, $steel);

    loginAsAcmeUser();

    $this->from('/acme/purchase-orders')
        ->post("/acme/purchase-orders/{$poId}/cancel")
        ->assertRedirect('/acme/purchase-orders')
        ->assertToast('Purchase order cancelled.');

    $this->tenant->run(fn () => expect(PurchaseOrder::find($poId)->status->value)->toBe('cancelled'));

    // A cancelled PO can't be received.
    $this->post("/acme/purchase-orders/{$poId}/receive", ['warehouse_id' => $warehouse])
        ->assertStatus(422);
});

it('updates a pending PO but rejects updating a received one', function () {
    ['supplier' => $supplier, 'steel' => $steel, 'bolt' => $bolt, 'warehouse' => $warehouse] = seedPurchaseFixture();
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
    $this->post("/acme/purchase-orders/{$poId}/receive", ['warehouse_id' => $warehouse]);

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
            ->has('warehouses', 1)
        );
});

it('filters, paginates and orders the purchase orders index by query params', function () {
    ['steel' => $steel] = seedPurchaseFixture();

    [$alpha, $beta] = $this->tenant->run(fn () => [
        Supplier::create(['name' => 'Alpha Metals'])->id,
        Supplier::create(['name' => 'Beta Supplies'])->id,
    ]);
    $id1 = makePendingPo($alpha, $steel);
    $id2 = makePendingPo($beta, $steel);
    $id3 = makePendingPo($alpha, $steel);

    loginAsAcmeUser();

    // ?search matches the supplier name; the term echoes back in filters.search.
    $this->get('/acme/purchase-orders?search=Beta')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $id2)
            ->where('filters.search', 'Beta'));

    // A non-matching term yields an empty page (still echoing the term).
    $this->get('/acme/purchase-orders?search=Zenith')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 0)
            ->where('filters.search', 'Zenith'));

    // ?per_page is honoured (25 is in the allow-list; 3 rows fit on one page).
    $this->get('/acme/purchase-orders?per_page=25')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 3)
            ->where('orders.per_page', 25));

    // Newest-first, deterministic: highest id first, lowest last.
    $this->get('/acme/purchase-orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.id', $id3)
            ->where('orders.data.2.id', $id1));
});

it('paginates the purchase orders index across pages', function () {
    ['supplier' => $supplier, 'steel' => $steel] = seedPurchaseFixture();
    foreach (range(1, 11) as $ignored) {
        makePendingPo($supplier, $steel);
    }

    loginAsAcmeUser();

    // Default per_page is 10 → 11 rows spill onto a second page.
    $this->get('/acme/purchase-orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 10)
            ->where('orders.current_page', 1)
            ->where('orders.last_page', 2));

    $this->get('/acme/purchase-orders?page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.current_page', 2));
});

it('deletes a purchase order', function () {
    ['supplier' => $supplier, 'steel' => $steel] = seedPurchaseFixture();
    $poId = makePendingPo($supplier, $steel);

    loginAsAcmeUser();

    $this->from('/acme/purchase-orders')
        ->delete("/acme/purchase-orders/{$poId}")
        ->assertRedirect('/acme/purchase-orders')
        ->assertToast('Purchase order deleted.');

    $this->tenant->run(fn () => expect(PurchaseOrder::find($poId))->toBeNull());
});
