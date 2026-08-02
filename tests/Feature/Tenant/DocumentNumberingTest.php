<?php

use App\Actions\ProvisionTenant;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/** @return array{customer: int, product: int} */
function seedSalesParties(): array
{
    return test()->tenant->run(fn () => [
        'customer' => Customer::create(['name' => 'Globex'])->id,
        'product' => Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea'])->id,
    ]);
}

function postSalesOrder(int $customer, int $product, array $overrides = []): void
{
    test()->post('/acme/sales-orders', array_merge([
        'customer_id' => $customer,
        'currency' => 'MYR',
        'items' => [['product_id' => $product, 'quantity' => 1, 'unit_price' => 10]],
    ], $overrides));
}

it('auto-generates sequential sales-order numbers from the settings prefix + year', function () {
    ['customer' => $customer, 'product' => $product] = seedSalesParties();
    loginAsAcmeUser();

    postSalesOrder($customer, $product);
    postSalesOrder($customer, $product);

    $numbers = $this->tenant->run(fn () => SalesOrder::orderBy('id')->pluck('number')->all());

    expect($numbers[0])->toMatch('/^SO-\d{4}-0001$/')
        ->and($numbers[1])->toMatch('/^SO-\d{4}-0002$/');
});

it('does not reuse a number a manual order already took', function () {
    ['customer' => $customer, 'product' => $product] = seedSalesParties();
    loginAsAcmeUser();

    // Financial year defaults to the calendar year (start month 1), so the first
    // auto number would be SO-{year}-0001. Take it manually first.
    $period = (string) now()->year;
    $taken = "SO-{$period}-0001";
    postSalesOrder($customer, $product, ['number' => $taken]);

    // The next (blank) order must skip the taken value, not duplicate it.
    postSalesOrder($customer, $product);

    $numbers = $this->tenant->run(fn () => SalesOrder::orderBy('id')->pluck('number')->all());

    expect($numbers[0])->toBe($taken)
        ->and($numbers[1])->toBe("SO-{$period}-0002")
        ->and($numbers[1])->not->toBe($taken);
});

it('keeps a manually entered sales-order number', function () {
    ['customer' => $customer, 'product' => $product] = seedSalesParties();
    loginAsAcmeUser();

    postSalesOrder($customer, $product, ['number' => 'INV-CUSTOM-1']);

    $this->tenant->run(fn () => expect(SalesOrder::firstOrFail()->number)->toBe('INV-CUSTOM-1'));
});
