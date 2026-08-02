<?php

use App\Actions\ProvisionTenant;
use App\Enums\StockMovementReason;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Setting;
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
 * Customer + product + a warehouse (stock holder), in the tenant DB.
 *
 * @return array{customer: int, widget: int, warehouse: int}
 */
function seedSalesFixture(): array
{
    return test()->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);

        return [
            'customer' => Customer::create(['name' => 'Globex'])->id,
            'widget' => Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea'])->id,
            'warehouse' => Warehouse::create(['location_id' => $location->id, 'name' => 'Main'])->id,
        ];
    });
}

/** Create a pending SO with one widget line (qty 5). Returns its id. */
function makePendingSo(int $customerId, int $widgetId): int
{
    return test()->tenant->run(function () use ($customerId, $widgetId) {
        $order = SalesOrder::create([
            'customer_id' => $customerId,
            'currency' => 'USD',
            'status' => 'pending',
        ]);
        $order->items()->create([
            'product_id' => $widgetId,
            'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea'],
            'quantity' => 5,
            'unit_price' => 9.99,
        ]);

        return $order->id;
    });
}

/** Put $qty of $productId on-hand at $warehouseId via the service. */
function seedOnHand(int $warehouseId, int $productId, float $qty): void
{
    test()->tenant->run(function () use ($warehouseId, $productId, $qty) {
        app(StockService::class)->record(
            Warehouse::find($warehouseId),
            Product::find($productId),
            $qty,
            StockMovementReason::Adjustment,
        );
    });
}

it('redirects a guest from the sales orders page to the tenant login', function () {
    $this->get('/acme/sales-orders')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('shows a printable sales order document', function () {
    ['customer' => $customer, 'widget' => $widget] = seedSalesFixture();
    $soId = makePendingSo($customer, $widget);

    loginAsAcmeUser();

    $this->get("/acme/sales-orders/{$soId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/sales-orders/show')
            ->where('order.id', $soId)
            ->has('order.items', 1)
            ->has('warehouses')
            ->where('print', false)
        );
});

it('creates a sales order with line items and snapshots', function () {
    ['customer' => $customer, 'widget' => $widget] = seedSalesFixture();

    loginAsAcmeUser();

    $this->from('/acme/sales-orders')
        ->post('/acme/sales-orders', [
            'customer_id' => $customer,
            'currency' => 'USD',
            'expected_date' => '2026-08-15T00:00:00.000Z',
            'items' => [
                ['product_id' => $widget, 'quantity' => 5, 'unit_price' => 9.99],
            ],
        ])
        ->assertRedirect('/acme/sales-orders')
        ->assertToast('Sales order created.');

    $this->tenant->run(function () {
        $order = SalesOrder::with('items')->first();
        expect($order->status->value)->toBe('pending')
            ->and($order->items)->toHaveCount(1)
            ->and($order->items->first()->product_snapshot['name'])->toBe('Widget')
            ->and($order->expected_date?->toDateString())->toBe('2026-08-15');
    });
});

it('fulfills a pending SO: posts sales_fulfillment OUT and drops on-hand', function () {
    ['customer' => $customer, 'widget' => $widget, 'warehouse' => $warehouse] = seedSalesFixture();
    seedOnHand($warehouse, $widget, 20);
    $soId = makePendingSo($customer, $widget);

    loginAsAcmeUser();

    $this->from('/acme/sales-orders')
        ->post("/acme/sales-orders/{$soId}/fulfill", ['warehouse_id' => $warehouse])
        ->assertRedirect('/acme/sales-orders')
        ->assertToast('Sales order fulfilled.');

    $this->tenant->run(function () use ($soId, $widget, $warehouse) {
        $order = SalesOrder::find($soId);
        expect($order->status->value)->toBe('fulfilled')
            ->and($order->fulfilled_at)->not->toBeNull()
            ->and($order->fulfilled_warehouse_id)->toBe($warehouse);

        $stock = WarehouseStock::where('warehouse_id', $warehouse)->where('stockable_id', $widget)->first();
        expect((float) $stock->quantity)->toBe(15.0) // 20 − 5
            ->and(StockMovement::where('reason', 'sales_fulfillment')->count())->toBe(1);
    });
});

it('rolls back the whole fulfill when the warehouse is short', function () {
    ['customer' => $customer, 'widget' => $widget, 'warehouse' => $warehouse] = seedSalesFixture();
    seedOnHand($warehouse, $widget, 3); // less than the order's 5
    $soId = makePendingSo($customer, $widget);

    loginAsAcmeUser();

    $this->from('/acme/sales-orders')
        ->post("/acme/sales-orders/{$soId}/fulfill", ['warehouse_id' => $warehouse])
        ->assertRedirect('/acme/sales-orders')
        ->assertInvalid('warehouse_id');

    $this->tenant->run(function () use ($soId, $widget, $warehouse) {
        // Nothing changed: on-hand intact, order still pending, no fulfillment movement.
        $stock = WarehouseStock::where('warehouse_id', $warehouse)->where('stockable_id', $widget)->first();
        expect((float) $stock->quantity)->toBe(3.0)
            ->and(SalesOrder::find($soId)->status->value)->toBe('pending')
            ->and(StockMovement::where('reason', 'sales_fulfillment')->count())->toBe(0);
    });
});

it('rejects fulfilling a non-pending SO', function () {
    ['customer' => $customer, 'widget' => $widget, 'warehouse' => $warehouse] = seedSalesFixture();
    seedOnHand($warehouse, $widget, 20);
    $soId = makePendingSo($customer, $widget);

    loginAsAcmeUser();

    $this->post("/acme/sales-orders/{$soId}/fulfill", ['warehouse_id' => $warehouse]);

    $this->post("/acme/sales-orders/{$soId}/fulfill", ['warehouse_id' => $warehouse])
        ->assertStatus(422);
});

it('cancels a pending SO but not a fulfilled one', function () {
    ['customer' => $customer, 'widget' => $widget, 'warehouse' => $warehouse] = seedSalesFixture();
    seedOnHand($warehouse, $widget, 20);
    $soId = makePendingSo($customer, $widget);

    loginAsAcmeUser();

    $this->from('/acme/sales-orders')
        ->post("/acme/sales-orders/{$soId}/cancel")
        ->assertRedirect('/acme/sales-orders')
        ->assertToast('Sales order cancelled.');

    $this->tenant->run(fn () => expect(SalesOrder::find($soId)->status->value)->toBe('cancelled'));

    $this->post("/acme/sales-orders/{$soId}/fulfill", ['warehouse_id' => $warehouse])
        ->assertStatus(422);
});

it('updates a pending SO but rejects updating a fulfilled one', function () {
    ['customer' => $customer, 'widget' => $widget, 'warehouse' => $warehouse] = seedSalesFixture();
    seedOnHand($warehouse, $widget, 20);
    $soId = makePendingSo($customer, $widget);

    loginAsAcmeUser();

    $this->from('/acme/sales-orders')
        ->put("/acme/sales-orders/{$soId}", [
            'customer_id' => $customer,
            'currency' => 'USD',
            'items' => [['product_id' => $widget, 'quantity' => 2, 'unit_price' => 12]],
        ])
        ->assertRedirect('/acme/sales-orders')
        ->assertToast('Sales order updated.');

    $this->tenant->run(function () use ($soId) {
        expect((float) SalesOrder::with('items')->find($soId)->items->first()->quantity)->toBe(2.0);
    });

    $this->post("/acme/sales-orders/{$soId}/fulfill", ['warehouse_id' => $warehouse]);

    $this->put("/acme/sales-orders/{$soId}", [
        'customer_id' => $customer,
        'currency' => 'USD',
        'items' => [['product_id' => $widget, 'quantity' => 1, 'unit_price' => 12]],
    ])->assertStatus(422);
});

it('requires at least one line item', function () {
    ['customer' => $customer] = seedSalesFixture();

    loginAsAcmeUser();

    $this->from('/acme/sales-orders')
        ->post('/acme/sales-orders', [
            'customer_id' => $customer,
            'currency' => 'USD',
            'items' => [],
        ])
        ->assertRedirect('/acme/sales-orders')
        ->assertInvalid('items');
});

it('lists sales orders with picker options', function () {
    ['customer' => $customer, 'widget' => $widget] = seedSalesFixture();
    makePendingSo($customer, $widget);

    loginAsAcmeUser();

    $this->get('/acme/sales-orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/sales-orders/index')
            ->has('orders.data', 1)
            ->has('customers', 1)
            ->has('products', 1)
            ->has('warehouses', 1)
        );
});

it('filters, paginates and orders the sales orders index by query params', function () {
    ['widget' => $widget] = seedSalesFixture();

    [$alpha, $beta] = $this->tenant->run(fn () => [
        Customer::create(['name' => 'Alpha Retail'])->id,
        Customer::create(['name' => 'Beta Traders'])->id,
    ]);
    $id1 = makePendingSo($alpha, $widget);
    $id2 = makePendingSo($beta, $widget);
    $id3 = makePendingSo($alpha, $widget);

    loginAsAcmeUser();

    $this->get('/acme/sales-orders?search=Beta')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $id2)
            ->where('filters.search', 'Beta'));

    $this->get('/acme/sales-orders?search=Zenith')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 0)
            ->where('filters.search', 'Zenith'));

    $this->get('/acme/sales-orders?per_page=25')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 3)
            ->where('orders.per_page', 25));

    $this->get('/acme/sales-orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.id', $id3)
            ->where('orders.data.2.id', $id1));
});

it('deletes a sales order', function () {
    ['customer' => $customer, 'widget' => $widget] = seedSalesFixture();
    $soId = makePendingSo($customer, $widget);

    loginAsAcmeUser();

    $this->from('/acme/sales-orders')
        ->delete("/acme/sales-orders/{$soId}")
        ->assertRedirect('/acme/sales-orders')
        ->assertToast('Sales order deleted.');

    $this->tenant->run(fn () => expect(SalesOrder::find($soId))->toBeNull());
});

it('accepts a unique order number and rejects a duplicate (R08b)', function () {
    ['customer' => $customer, 'widget' => $widget] = seedSalesFixture();
    loginAsAcmeUser();

    $this->post('/acme/sales-orders', [
        'customer_id' => $customer,
        'currency' => 'USD',
        'number' => 'SO-2026-014',
        'items' => [['product_id' => $widget, 'quantity' => 1, 'unit_price' => 1]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->tenant->run(fn () => expect(SalesOrder::first()->number)->toBe('SO-2026-014'));

    // Reusing the same number on another order is rejected.
    $this->from('/acme/sales-orders')
        ->post('/acme/sales-orders', [
            'customer_id' => $customer,
            'currency' => 'USD',
            'number' => 'SO-2026-014',
            'items' => [['product_id' => $widget, 'quantity' => 1, 'unit_price' => 1]],
        ])
        ->assertRedirect('/acme/sales-orders')
        ->assertSessionHasErrors('number');

    $this->tenant->run(fn () => expect(SalesOrder::count())->toBe(1));
});

it('computes subtotal, tax and grand total from taxable lines (R15)', function () {
    ['customer' => $customer, 'widget' => $widget] = seedSalesFixture();
    $this->tenant->run(fn () => Setting::putMany('business', ['tax_type' => 'sst', 'tax_rate' => '10']));

    loginAsAcmeUser();

    // Line A: 2 × 100 = 200 (taxable). Line B: 1 × 50 = 50 (exempt).
    $this->post('/acme/sales-orders', [
        'customer_id' => $customer,
        'currency' => 'MYR',
        'items' => [
            ['product_id' => $widget, 'quantity' => 2, 'unit_price' => 100, 'taxable' => true],
            ['product_id' => $widget, 'quantity' => 1, 'unit_price' => 50, 'taxable' => false],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $soId = $this->tenant->run(fn () => SalesOrder::first()->id);

    // Snapshot: the exempt line is out of the tax base, so tax = 10% of 200 = 20.
    $this->tenant->run(function () {
        $order = SalesOrder::with('items')->first();
        expect((float) $order->tax_rate)->toBe(10.0)
            ->and($order->items[0]->taxable)->toBeTrue()
            ->and($order->items[1]->taxable)->toBeFalse();
    });

    $this->get("/acme/sales-orders/{$soId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.subtotal', 250)
            ->where('order.tax_rate', 10)
            ->where('order.tax_amount', 20)
            ->where('order.total', 270)
        );
});

it('applies no tax when the tenant charges none, even with a stale rate (R15)', function () {
    ['customer' => $customer, 'widget' => $widget] = seedSalesFixture();
    // A left-over rate must not tax a "none" order.
    $this->tenant->run(fn () => Setting::putMany('business', ['tax_type' => 'none', 'tax_rate' => '10']));

    loginAsAcmeUser();

    $this->post('/acme/sales-orders', [
        'customer_id' => $customer,
        'currency' => 'MYR',
        'items' => [['product_id' => $widget, 'quantity' => 2, 'unit_price' => 100, 'taxable' => true]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $soId = $this->tenant->run(fn () => SalesOrder::first()->id);

    $this->tenant->run(fn () => expect((float) SalesOrder::first()->tax_rate)->toBe(0.0));

    $this->get("/acme/sales-orders/{$soId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.subtotal', 200)
            ->where('order.tax_amount', 0)
            ->where('order.total', 200)
        );
});

it('snapshots the tax rate at issue so a later settings change never re-taxes (R15)', function () {
    ['customer' => $customer, 'widget' => $widget] = seedSalesFixture();
    $this->tenant->run(fn () => Setting::putMany('business', ['tax_type' => 'sst', 'tax_rate' => '10']));

    loginAsAcmeUser();

    $this->post('/acme/sales-orders', [
        'customer_id' => $customer,
        'currency' => 'MYR',
        'items' => [['product_id' => $widget, 'quantity' => 1, 'unit_price' => 100, 'taxable' => true]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $soId = $this->tenant->run(fn () => SalesOrder::first()->id);

    // Rate drops after the order is issued.
    $this->tenant->run(fn () => Setting::putMany('business', ['tax_type' => 'sst', 'tax_rate' => '6']));

    // The order keeps its issued-time 10%, not the new 6%.
    $this->get("/acme/sales-orders/{$soId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.tax_rate', 10)
            ->where('order.tax_amount', 10)
            ->where('order.total', 110)
        );
});

it('defaults a line to taxable when the payload omits the flag (R15)', function () {
    ['customer' => $customer, 'widget' => $widget] = seedSalesFixture();
    $this->tenant->run(fn () => Setting::putMany('business', ['tax_type' => 'sst', 'tax_rate' => '10']));

    loginAsAcmeUser();

    // No `taxable` key — older payloads must fall back to taxable.
    $this->post('/acme/sales-orders', [
        'customer_id' => $customer,
        'currency' => 'MYR',
        'items' => [['product_id' => $widget, 'quantity' => 1, 'unit_price' => 100]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->tenant->run(fn () => expect(SalesOrder::with('items')->first()->items->first()->taxable)->toBeTrue());
});
