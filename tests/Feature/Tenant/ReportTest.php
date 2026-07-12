<?php

use App\Actions\ProvisionTenant;
use App\Enums\SalesOrderStatus;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

function seedFulfilledSale(int $productId, float $qty, float $price, Carbon $when): void
{
    $order = SalesOrder::create([
        'customer_id' => null,
        'status' => SalesOrderStatus::Fulfilled,
        'currency' => 'USD',
        'fulfilled_at' => $when,
    ]);

    $order->items()->create([
        'product_id' => $productId,
        'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs'],
        'quantity' => $qty,
        'unit_price' => $price,
    ]);
}

it('redirects a guest from the reports page to the tenant login', function () {
    $this->get('/acme/reports')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('totals fulfilled sales within the selected period, excluding out-of-range', function () {
    $this->tenant->run(function () {
        $product = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        seedFulfilledSale($product->id, 2, 10, Carbon::now());              // in this month
        seedFulfilledSale($product->id, 5, 10, Carbon::now()->subMonths(3)); // out of range
    });

    loginAsAcmeUser();

    $this->get('/acme/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/reports/index')
            ->where('sales.count', 1)
            ->where('sales.quantity', 2)
            ->where('sales.amount', 20)
        );
});
