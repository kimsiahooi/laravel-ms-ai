<?php

use App\Actions\ProvisionTenant;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Services\StockReportService;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

// Base currency for a fresh tenant is MYR (BusinessSettings default_currency).
beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/**
 * A supplier + raw material + customer + product in the tenant DB.
 *
 * @return array{supplier: int, steel: int, customer: int, widget: int}
 */
function seedCurrencyFixture(): array
{
    return test()->tenant->run(fn () => [
        'supplier' => Supplier::create(['name' => 'Acme Metals'])->id,
        'steel' => RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg'])->id,
        'customer' => Customer::create(['name' => 'Buyer Co'])->id,
        'widget' => Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea'])->id,
    ]);
}

it('stores a foreign purchase order with its exchange rate', function () {
    ['supplier' => $supplier, 'steel' => $steel] = seedCurrencyFixture();
    loginAsAcmeUser();

    $this->post('/acme/purchase-orders', [
        'supplier_id' => $supplier,
        'currency' => 'USD',
        'exchange_rate' => 4.7,
        'items' => [['raw_material_id' => $steel, 'quantity' => 10, 'unit_cost' => 2]],
    ])->assertRedirect();

    $this->tenant->run(function () {
        $order = PurchaseOrder::firstOrFail();
        expect($order->currency)->toBe('USD')
            ->and((float) $order->exchange_rate)->toBe(4.7);
    });
});

it('forces the rate to 1 for a base-currency order', function () {
    ['supplier' => $supplier, 'steel' => $steel] = seedCurrencyFixture();
    loginAsAcmeUser();

    // Base is MYR, so a supplied rate is ignored — no conversion applies.
    $this->post('/acme/purchase-orders', [
        'supplier_id' => $supplier,
        'currency' => 'MYR',
        'exchange_rate' => 9,
        'items' => [['raw_material_id' => $steel, 'quantity' => 1, 'unit_cost' => 1]],
    ])->assertRedirect();

    $this->tenant->run(fn () => expect((float) PurchaseOrder::firstOrFail()->exchange_rate)->toBe(1.0));
});

it('defaults a missing rate to 1', function () {
    ['supplier' => $supplier, 'steel' => $steel] = seedCurrencyFixture();
    loginAsAcmeUser();

    $this->post('/acme/purchase-orders', [
        'supplier_id' => $supplier,
        'currency' => 'USD',
        'items' => [['raw_material_id' => $steel, 'quantity' => 1, 'unit_cost' => 1]],
    ])->assertValid();

    $this->tenant->run(fn () => expect((float) PurchaseOrder::firstOrFail()->exchange_rate)->toBe(1.0));
});

it('rejects a currency outside the allowed list', function () {
    ['supplier' => $supplier, 'steel' => $steel] = seedCurrencyFixture();
    loginAsAcmeUser();

    $this->post('/acme/purchase-orders', [
        'supplier_id' => $supplier,
        'currency' => 'XYZ',
        'exchange_rate' => 1,
        'items' => [['raw_material_id' => $steel, 'quantity' => 1, 'unit_cost' => 1]],
    ])->assertInvalid('currency');
});

it('rejects an over-range exchange rate with a 422, not a 500', function () {
    ['supplier' => $supplier, 'steel' => $steel] = seedCurrencyFixture();
    loginAsAcmeUser();

    // 1e9 exceeds the decimal(15,6) column ceiling — must be a validation error,
    // never a DB-overflow 500 (R13-class).
    $this->post('/acme/purchase-orders', [
        'supplier_id' => $supplier,
        'currency' => 'USD',
        'exchange_rate' => 1000000000,
        'items' => [['raw_material_id' => $steel, 'quantity' => 1, 'unit_cost' => 1]],
    ])->assertInvalid('exchange_rate');
});

it('converts mixed-currency purchases to the base currency in totals', function () {
    ['steel' => $steel] = seedCurrencyFixture();

    $this->tenant->run(function () use ($steel) {
        $snapshot = ['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg'];

        // MYR order: 10 × 2 = 20 base.
        $myr = PurchaseOrder::create([
            'currency' => 'MYR', 'exchange_rate' => 1,
            'status' => PurchaseOrderStatus::Received, 'received_at' => Date::now(),
        ]);
        $myr->items()->create(['raw_material_id' => $steel, 'raw_material_snapshot' => $snapshot, 'quantity' => 10, 'unit_cost' => 2]);

        // USD order at rate 4: 5 × 3 = 15 USD = 60 base.
        $usd = PurchaseOrder::create([
            'currency' => 'USD', 'exchange_rate' => 4,
            'status' => PurchaseOrderStatus::Received, 'received_at' => Date::now(),
        ]);
        $usd->items()->create(['raw_material_id' => $steel, 'raw_material_snapshot' => $snapshot, 'quantity' => 5, 'unit_cost' => 3]);

        $totals = app(StockReportService::class)->purchaseTotals([Date::now()->startOfDay(), Date::now()->endOfDay()]);

        // 20 + 60 = 80 (base), NOT the raw mixed sum of 35.
        expect((float) $totals->amount)->toBe(80.0);
    });
});

it('converts mixed-currency sales to the base currency in totals', function () {
    ['widget' => $widget] = seedCurrencyFixture();

    $this->tenant->run(function () use ($widget) {
        $snapshot = ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea'];

        // MYR order: 2 × 50 = 100 base.
        $myr = SalesOrder::create([
            'currency' => 'MYR', 'exchange_rate' => 1,
            'status' => SalesOrderStatus::Fulfilled, 'fulfilled_at' => Date::now(),
        ]);
        $myr->items()->create(['product_id' => $widget, 'product_snapshot' => $snapshot, 'quantity' => 2, 'unit_price' => 50]);

        // SGD order at rate 3: 1 × 20 = 20 SGD = 60 base.
        $sgd = SalesOrder::create([
            'currency' => 'SGD', 'exchange_rate' => 3,
            'status' => SalesOrderStatus::Fulfilled, 'fulfilled_at' => Date::now(),
        ]);
        $sgd->items()->create(['product_id' => $widget, 'product_snapshot' => $snapshot, 'quantity' => 1, 'unit_price' => 20]);

        $totals = app(StockReportService::class)->salesTotals([Date::now()->startOfDay(), Date::now()->endOfDay()]);

        // 100 + 60 = 160 (base).
        expect((float) $totals->amount)->toBe(160.0);
    });
});

it('exposes currency, rate and base total on the order DTO', function () {
    ['supplier' => $supplier, 'steel' => $steel] = seedCurrencyFixture();
    loginAsAcmeUser();

    $this->post('/acme/purchase-orders', [
        'supplier_id' => $supplier,
        'currency' => 'USD',
        'exchange_rate' => 4,
        'items' => [['raw_material_id' => $steel, 'quantity' => 10, 'unit_cost' => 2]],
    ])->assertRedirect();

    $this->get('/acme/purchase-orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.currency', 'USD')
            ->where('orders.data.0.base_currency', 'MYR')
            ->where('orders.data.0.exchange_rate', fn ($v) => (float) $v === 4.0)
            ->where('orders.data.0.total', fn ($v) => (float) $v === 20.0)
            ->where('orders.data.0.base_total', fn ($v) => (float) $v === 80.0)
            ->etc()
        );
});

it('reports totals in the base currency', function () {
    loginAsAcmeUser();

    $this->get('/acme/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('currency', 'MYR'));
});
