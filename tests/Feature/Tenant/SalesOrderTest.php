<?php

use App\Actions\ProvisionTenant;
use App\Enums\StockMovementReason;
use App\Models\Customer;
use App\Models\Location;
use App\Models\LocationStock;
use App\Models\Product;
use App\Models\SalesOrder;
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
 * Customer + product + a location, in the tenant DB.
 *
 * @return array{customer: int, widget: int, location: int}
 */
function seedSalesFixture(): array
{
    return test()->tenant->run(function () {
        $warehouse = Warehouse::create(['name' => 'Main']);

        return [
            'customer' => Customer::create(['name' => 'Globex'])->id,
            'widget' => Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea', 'min_stock' => 0])->id,
            'location' => Location::create(['warehouse_id' => $warehouse->id, 'code' => 'A-01'])->id,
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

/** Put $qty of $productId on-hand at $locationId via the service. */
function seedOnHand(int $locationId, int $productId, float $qty): void
{
    test()->tenant->run(function () use ($locationId, $productId, $qty) {
        app(StockService::class)->record(
            Location::find($locationId),
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

it('creates a sales order with line items and snapshots', function () {
    ['customer' => $customer, 'widget' => $widget] = seedSalesFixture();

    loginAsAcmeUser();

    $this->from('/acme/sales-orders')
        ->post('/acme/sales-orders', [
            'customer_id' => $customer,
            'currency' => 'USD',
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
            ->and($order->items->first()->product_snapshot['name'])->toBe('Widget');
    });
});

it('fulfills a pending SO: posts sales_fulfillment OUT and drops on-hand', function () {
    ['customer' => $customer, 'widget' => $widget, 'location' => $location] = seedSalesFixture();
    seedOnHand($location, $widget, 20);
    $soId = makePendingSo($customer, $widget);

    loginAsAcmeUser();

    $this->from('/acme/sales-orders')
        ->post("/acme/sales-orders/{$soId}/fulfill", ['location_id' => $location])
        ->assertRedirect('/acme/sales-orders')
        ->assertToast('Sales order fulfilled.');

    $this->tenant->run(function () use ($soId, $widget, $location) {
        $order = SalesOrder::find($soId);
        expect($order->status->value)->toBe('fulfilled')
            ->and($order->fulfilled_at)->not->toBeNull()
            ->and($order->fulfilled_location_id)->toBe($location);

        $stock = LocationStock::where('location_id', $location)->where('stockable_id', $widget)->first();
        expect((float) $stock->quantity)->toBe(15.0) // 20 − 5
            ->and(StockMovement::where('reason', 'sales_fulfillment')->count())->toBe(1);
    });
});

it('rolls back the whole fulfill when the location is short', function () {
    ['customer' => $customer, 'widget' => $widget, 'location' => $location] = seedSalesFixture();
    seedOnHand($location, $widget, 3); // less than the order's 5
    $soId = makePendingSo($customer, $widget);

    loginAsAcmeUser();

    $this->from('/acme/sales-orders')
        ->post("/acme/sales-orders/{$soId}/fulfill", ['location_id' => $location])
        ->assertRedirect('/acme/sales-orders')
        ->assertInvalid('location_id');

    $this->tenant->run(function () use ($soId, $widget, $location) {
        // Nothing changed: on-hand intact, order still pending, no fulfillment movement.
        $stock = LocationStock::where('location_id', $location)->where('stockable_id', $widget)->first();
        expect((float) $stock->quantity)->toBe(3.0)
            ->and(SalesOrder::find($soId)->status->value)->toBe('pending')
            ->and(StockMovement::where('reason', 'sales_fulfillment')->count())->toBe(0);
    });
});

it('rejects fulfilling a non-pending SO', function () {
    ['customer' => $customer, 'widget' => $widget, 'location' => $location] = seedSalesFixture();
    seedOnHand($location, $widget, 20);
    $soId = makePendingSo($customer, $widget);

    loginAsAcmeUser();

    $this->post("/acme/sales-orders/{$soId}/fulfill", ['location_id' => $location]);

    $this->post("/acme/sales-orders/{$soId}/fulfill", ['location_id' => $location])
        ->assertStatus(422);
});

it('cancels a pending SO but not a fulfilled one', function () {
    ['customer' => $customer, 'widget' => $widget, 'location' => $location] = seedSalesFixture();
    seedOnHand($location, $widget, 20);
    $soId = makePendingSo($customer, $widget);

    loginAsAcmeUser();

    $this->from('/acme/sales-orders')
        ->post("/acme/sales-orders/{$soId}/cancel")
        ->assertRedirect('/acme/sales-orders')
        ->assertToast('Sales order cancelled.');

    $this->tenant->run(fn () => expect(SalesOrder::find($soId)->status->value)->toBe('cancelled'));

    $this->post("/acme/sales-orders/{$soId}/fulfill", ['location_id' => $location])
        ->assertStatus(422);
});

it('updates a pending SO but rejects updating a fulfilled one', function () {
    ['customer' => $customer, 'widget' => $widget, 'location' => $location] = seedSalesFixture();
    seedOnHand($location, $widget, 20);
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

    $this->post("/acme/sales-orders/{$soId}/fulfill", ['location_id' => $location]);

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
            ->has('locations', 1)
        );
});
