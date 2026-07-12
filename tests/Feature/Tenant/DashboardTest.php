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

// Inlined (not a shared helper) so it can't clash with ReportTest's own
// seedFulfilledSale — Pest loads every test file into one process.
function makeDashboardSale(int $productId, float $qty, float $price, Carbon $when): void
{
    $order = SalesOrder::create([
        'customer_id' => null,
        'status' => SalesOrderStatus::Fulfilled,
        'currency' => 'MYR',
        'fulfilled_at' => $when,
    ]);

    $order->items()->create([
        'product_id' => $productId,
        'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs'],
        'quantity' => $qty,
        'unit_price' => $price,
    ]);
}

it('redirects a guest from the dashboard to the tenant login', function () {
    $this->get('/acme/dashboard')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('renders the organization header, KPI tiles and chart series', function () {
    loginAsAcmeUser();

    $this->get('/acme/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/dashboard')
            ->where('organization.name', 'Acme')
            ->where('organization.slug', 'acme')
            ->where('organization.members', 1) // only Ada exists in the tenant DB
            ->has('organization.logo')         // present (null until a logo is set)
            ->has('filters.from')
            ->has('filters.to')
            ->has('kpis.sales')
            ->has('kpis.purchases')
            ->has('kpis.production')
            ->where('kpis.low_stock', 0)
            ->has('series')   // one point per day in range
            ->has('movements')
            ->where('auth.user.email', 'ada@acme.test')
        );
});

it('totals fulfilled sales within the default (this-week) period, excluding out-of-range', function () {
    $this->tenant->run(function () {
        $product = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        makeDashboardSale($product->id, 2, 10, Carbon::now());               // in this week
        makeDashboardSale($product->id, 5, 10, Carbon::now()->subWeeks(2));  // out of range
    });

    loginAsAcmeUser();

    $this->get('/acme/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/dashboard')
            ->where('kpis.sales.count', 1)
            ->where('kpis.sales.amount', 20)
        );
});
