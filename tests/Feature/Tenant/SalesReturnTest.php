<?php

use App\Actions\ProvisionTenant;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/** Seed an (empty) warehouse, a product and a customer. @return array{warehouse:int, product:int, customer:int} */
function seedSalesReturnFixture(): array
{
    return test()->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);
        $warehouse = Warehouse::create(['location_id' => $location->id, 'name' => 'Main']);
        $product = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        $customer = Customer::create(['name' => 'Jane Doe']);

        return [
            'warehouse' => $warehouse->id,
            'product' => $product->id,
            'customer' => $customer->id,
        ];
    });
}

/** Create a pending sales return for $customerId with one $productId line. Returns its id. */
function makePendingSalesReturn(int $customerId, int $productId, float $qty = 3): int
{
    return test()->tenant->run(function () use ($customerId, $productId, $qty) {
        $return = SalesReturn::create(['customer_id' => $customerId, 'status' => 'pending']);
        $product = Product::find($productId);
        $return->items()->create([
            'product_id' => $product->id,
            'product_snapshot' => ['name' => $product->name, 'sku' => $product->sku, 'unit' => $product->unit],
            'quantity' => $qty,
        ]);

        return $return->id;
    });
}

it('redirects a guest from the sales returns page to the tenant login', function () {
    $this->get('/acme/sales-returns')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('filters, paginates and orders the sales returns index by query params', function () {
    ['product' => $product] = seedSalesReturnFixture();

    [$alpha, $beta] = $this->tenant->run(fn () => [
        Customer::create(['name' => 'Alpha Retail'])->id,
        Customer::create(['name' => 'Beta Traders'])->id,
    ]);
    $id1 = makePendingSalesReturn($alpha, $product);
    $id2 = makePendingSalesReturn($beta, $product);
    $id3 = makePendingSalesReturn($alpha, $product);

    loginAsAcmeUser();

    $this->get('/acme/sales-returns?search=Beta')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('returns.data', 1)
            ->where('returns.data.0.id', $id2)
            ->where('filters.search', 'Beta'));

    $this->get('/acme/sales-returns?search=Zenith')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('returns.data', 0)
            ->where('filters.search', 'Zenith'));

    $this->get('/acme/sales-returns?per_page=25')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('returns.data', 3)
            ->where('returns.per_page', 25));

    $this->get('/acme/sales-returns')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('returns.data.0.id', $id3)
            ->where('returns.data.2.id', $id1));
});

it('shows a sales return', function () {
    ['product' => $product, 'customer' => $customer] = seedSalesReturnFixture();
    $returnId = makePendingSalesReturn($customer, $product);

    loginAsAcmeUser();

    $this->get("/acme/sales-returns/{$returnId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/sales-returns/show')
            ->where('return.id', $returnId)
            ->has('return.items', 1));
});

it('updates a pending sales return but rejects updating a non-pending one', function () {
    ['product' => $product, 'customer' => $customer] = seedSalesReturnFixture();
    $returnId = makePendingSalesReturn($customer, $product, 3);

    loginAsAcmeUser();

    $this->from('/acme/sales-returns')
        ->put("/acme/sales-returns/{$returnId}", [
            'customer_id' => $customer,
            'items' => [['product_id' => $product, 'quantity' => 6]],
        ])
        ->assertRedirect('/acme/sales-returns')
        ->assertToast('Sales return updated.');

    $this->tenant->run(fn () => expect((float) SalesReturn::with('items')->find($returnId)->items->first()->quantity)->toBe(6.0));

    $this->post("/acme/sales-returns/{$returnId}/cancel");
    $this->put("/acme/sales-returns/{$returnId}", [
        'customer_id' => $customer,
        'items' => [['product_id' => $product, 'quantity' => 1]],
    ])->assertStatus(422);
});

it('cancels a pending sales return but not again', function () {
    ['product' => $product, 'customer' => $customer] = seedSalesReturnFixture();
    $returnId = makePendingSalesReturn($customer, $product);

    loginAsAcmeUser();

    $this->from('/acme/sales-returns')
        ->post("/acme/sales-returns/{$returnId}/cancel")
        ->assertRedirect('/acme/sales-returns')
        ->assertToast('Sales return cancelled.');

    $this->tenant->run(fn () => expect(SalesReturn::find($returnId)->status->value)->toBe('cancelled'));

    $this->post("/acme/sales-returns/{$returnId}/cancel")->assertStatus(422);
});

it('deletes a sales return', function () {
    ['product' => $product, 'customer' => $customer] = seedSalesReturnFixture();
    $returnId = makePendingSalesReturn($customer, $product);

    loginAsAcmeUser();

    $this->from('/acme/sales-returns')
        ->delete("/acme/sales-returns/{$returnId}")
        ->assertRedirect('/acme/sales-returns')
        ->assertToast('Sales return deleted.');

    $this->tenant->run(fn () => expect(SalesReturn::find($returnId))->toBeNull());
});

it('creates a sales return and completes it, posting stock IN', function () {
    ['warehouse' => $wh, 'product' => $p, 'customer' => $c] = seedSalesReturnFixture();
    loginAsAcmeUser();

    $this->post('/acme/sales-returns', [
        'customer_id' => $c,
        'items' => [['product_id' => $p, 'quantity' => 4]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $returnId = $this->tenant->run(fn () => SalesReturn::first()->id);

    $this->post("/acme/sales-returns/{$returnId}/complete", ['warehouse_id' => $wh])
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->tenant->run(function () use ($wh, $p, $returnId) {
        expect(SalesReturn::find($returnId)->status->value)->toBe('completed');

        $stock = WarehouseStock::where('warehouse_id', $wh)
            ->where('stockable_type', 'product')
            ->where('stockable_id', $p)
            ->first();
        expect((float) $stock->quantity)->toBe(4.0);

        $movement = StockMovement::where('reason', 'sales_return')->first();
        expect($movement)->not->toBeNull()
            ->and((float) $movement->quantity)->toBe(4.0);
    });
});
