<?php

use App\Actions\ProvisionTenant;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;

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

it('redirects a guest from the sales returns page to the tenant login', function () {
    $this->get('/acme/sales-returns')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
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
