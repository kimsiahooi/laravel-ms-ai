<?php

use App\Actions\ProvisionTenant;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\StockService;
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
            ->where('snapshot.low_stock', 0)
            ->has('series')   // one point per day in range
            ->has('movements')
            ->has('onboarding')
            ->where('onboarding.location', false) // a fresh tenant has nothing set up
            ->where('onboarding.order', false)
            ->where('auth.user.email', 'ada@acme.test')
        );
});

it('sends a real zero for every snapshot figure on a fresh tenant (R17)', function () {
    loginAsAcmeUser();

    // Nothing set up anywhere — each figure should be a real zero rather than a
    // missing prop the page would render as "undefined".
    $this->get('/acme/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('snapshot.stock_value.amount', 0)
            ->where('snapshot.stock_value.valued', 0)
            ->where('snapshot.stock_value.unvalued', 0)
            ->where('snapshot.incoming.count', 0)
            ->where('snapshot.incoming.amount', 0)
            ->where('snapshot.incoming.overdue', 0)
            ->where('snapshot.production.active', 0)
            ->where('snapshot.production.blocked', 0)
            ->where('snapshot.low_stock', 0)
            ->where('snapshot.out_of_stock', 0)
            ->where('snapshot.ready_sales', 0)
        );
});

it('wires each snapshot figure to the right number (R17)', function () {
    $this->tenant->run(function () {
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        $location = Location::create(['name' => 'KL HQ']);
        $warehouse = Warehouse::create(['location_id' => $location->id, 'name' => 'Main']);

        // 20 kg of steel bought at 4.00 and received → 80.00 of stock on hand.
        $received = PurchaseOrder::create([
            'status' => PurchaseOrderStatus::Received, 'currency' => 'MYR',
            'exchange_rate' => 1, 'received_at' => now(),
        ]);
        $received->items()->create([
            'raw_material_id' => $steel->id,
            'raw_material_snapshot' => ['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg'],
            'quantity' => 20, 'unit_cost' => 4,
        ]);
        app(StockService::class)->record(
            $warehouse, $steel, 20, StockMovementReason::Adjustment,
        );

        // One more order still on its way, already a day late: 10 × 3.00 = 30.00.
        $open = PurchaseOrder::create([
            'status' => PurchaseOrderStatus::Pending, 'currency' => 'MYR',
            'exchange_rate' => 1, 'expected_date' => now()->subDay()->startOfDay(),
        ]);
        $open->items()->create([
            'raw_material_id' => $steel->id,
            'raw_material_snapshot' => ['name' => 'Steel', 'sku' => 'ST-1', 'unit' => 'kg'],
            'quantity' => 10, 'unit_cost' => 3,
        ]);

        // A sales order that one warehouse can cover.
        app(StockService::class)->record(
            $warehouse, $widget, 5, StockMovementReason::Adjustment,
        );
        $sale = SalesOrder::create([
            'customer_id' => null, 'status' => SalesOrderStatus::Pending, 'currency' => 'MYR',
        ]);
        $sale->items()->create([
            'product_id' => $widget->id,
            'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs'],
            'quantity' => 2, 'unit_price' => 10,
        ]);
    });

    loginAsAcmeUser();

    // Distinct figures, so a transposed or wrong-service call can't still pass.
    $this->get('/acme/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('snapshot.stock_value.amount', 80)
            ->where('snapshot.stock_value.valued', 1)   // steel priced; the widget isn't
            ->where('snapshot.stock_value.unvalued', 1)
            ->where('snapshot.incoming.count', 1)
            ->where('snapshot.incoming.amount', 30)
            ->where('snapshot.incoming.overdue', 1)
            ->where('snapshot.ready_sales', 1)
        );
});

it('reports onboarding progress from real data', function () {
    loginAsAcmeUser();

    // A fresh tenant: every step outstanding.
    $this->get('/acme/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.location', false)
            ->where('onboarding.warehouse', false)
        );

    // Add a location and the first step flips, the rest stay outstanding.
    $this->tenant->run(fn () => Location::create(['name' => 'KL HQ']));

    $this->get('/acme/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.location', true)
            ->where('onboarding.warehouse', false)
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

it('applies an offset-carrying date-range filter without erroring (CarbonImmutable)', function () {
    $this->tenant->run(function () {
        $product = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        // Jul 7, comfortably inside the range below regardless of the DB timezone.
        makeDashboardSale($product->id, 2, 10, Carbon::parse('2026-07-07T10:00:00+08:00'));
    });

    loginAsAcmeUser();

    // Params shaped exactly like the DateRangePicker emits (offset-carrying ISO). The
    // app runs Date::use(CarbonImmutable::class), so `$request->date()` returns a
    // CarbonImmutable — before the fix this hit the `Illuminate\Support\Carbon` hint on
    // dailySeries() and threw a TypeError (500). `%2B` is the encoded `+` of "+08:00".
    $this->get('/acme/dashboard?from=2026-07-06T00:00:00%2B08:00&to=2026-07-08T23:59:59%2B08:00')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/dashboard')
            ->where('kpis.sales.count', 1)
            ->where('kpis.sales.amount', 20)
            // Jul 6, 7, 8 inclusive = 3 points — not the 366 identical rows the
            // immutable-mutation no-op (`$cursor->addDay();`) would have produced.
            ->has('series', 3)
        );
});
