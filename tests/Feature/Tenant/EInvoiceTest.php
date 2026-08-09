<?php

use App\Actions\ProvisionTenant;
use App\Data\EInvoiceReadinessData;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Settings\BusinessSettings;
use App\Support\EInvoiceBuilder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/** The seller identity a ready-to-file tenant has. */
function sellerSettings(array $overrides = []): array
{
    return [
        'legal_name' => 'Acme Manufacturing Sdn Bhd',
        'registration_no' => '202301012345',
        'tin' => 'C20880690010',
        'tax_registration_no' => 'W10-1808-32000123',
        'tax_type' => 'sst',
        'tax_rate' => '10',
        'country' => 'MY',
        'default_currency' => 'MYR',
        'address' => 'Lot 12, Jalan Perindustrian 3',
        'city' => 'Petaling Jaya',
        'postcode' => '47810',
        'state_code' => '10',
        ...$overrides,
    ];
}

/**
 * A fulfilled-shaped order with a numbered document, a fully-identified buyer and
 * two lines (one taxable at 100×2, one exempt at 50×1). Returns its id.
 */
function seedEInvoiceOrder(array $settings = [], array $customer = [], array $order = []): int
{
    return test()->tenant->run(function () use ($settings, $customer, $order) {
        Setting::putMany('business', sellerSettings($settings));

        $buyer = Customer::create([
            'name' => 'Globex Retail Pte Ltd',
            'tin' => 'C12345678900',
            'registration_no' => '200912345A',
            'sst_registration_no' => 'B16-1234-56789012',
            'address' => '8 Marina View',
            'city' => 'Singapore',
            'postcode' => '018960',
            'state_code' => '14',
            'country_code' => 'SG',
            ...$customer,
        ]);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        $so = SalesOrder::create([
            'customer_id' => $buyer->id,
            'currency' => 'MYR',
            'tax_rate' => 10,
            'number' => 'SO-2026-0001',
            'status' => 'pending',
            ...$order,
        ]);
        $so->items()->create([
            'product_id' => $widget->id,
            'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea'],
            'quantity' => 2, 'unit_price' => 100, 'taxable' => true,
        ]);
        $so->items()->create([
            'product_id' => $widget->id,
            'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea'],
            'quantity' => 1, 'unit_price' => 50, 'taxable' => false,
        ]);

        return $so->id;
    });
}

it('builds a MyInvois-shaped payload with both parties and UBL totals (R15)', function () {
    $soId = seedEInvoiceOrder();

    $payload = $this->tenant->run(fn () => app(EInvoiceBuilder::class)
        ->build(SalesOrder::with(['customer', 'items'])->find($soId))
        ->toArray());

    expect($payload['format'])->toBe('einvoice-hook/v1')
        ->and($payload['standard'])->toBe('myinvois')
        ->and($payload['country_code'])->toBe('MY')
        ->and($payload['id'])->toBe('SO-2026-0001')
        // LHDN e-Invoice type code for a plain invoice.
        ->and($payload['invoice_type_code'])->toBe('01')
        ->and($payload['document_currency_code'])->toBe('MYR')
        ->and($payload['tax_currency_code'])->toBe('MYR')
        // Already in the tax currency, so there is no conversion to declare.
        ->and($payload['tax_exchange_rate'])->toBeNull();

    // Seller: TIN + BRN as scheme-qualified ids, SST under PartyTaxScheme.
    $seller = $payload['accounting_supplier_party'];
    expect($seller['registration_name'])->toBe('Acme Manufacturing Sdn Bhd')
        ->and($seller['party_identifications'])->toBe([
            ['scheme_id' => 'TIN', 'id' => 'C20880690010'],
            ['scheme_id' => 'BRN', 'id' => '202301012345'],
        ])
        ->and($seller['party_tax_scheme'])->toBe([
            'company_id' => 'W10-1808-32000123', 'tax_scheme_id' => 'SST',
        ])
        // MY has no Peppol endpoint concept.
        ->and($seller['endpoint_id'])->toBeNull()
        ->and($seller['postal_address']['city_name'])->toBe('Petaling Jaya')
        ->and($seller['postal_address']['country_identification_code'])->toBe('MY');

    $buyer = $payload['accounting_customer_party'];
    expect($buyer['registration_name'])->toBe('Globex Retail Pte Ltd')
        ->and($buyer['party_identifications'][0])->toBe(['scheme_id' => 'TIN', 'id' => 'C12345678900'])
        ->and($buyer['postal_address']['country_identification_code'])->toBe('SG');

    // 200 taxable + 50 exempt; tax = 10% of 200.
    expect($payload['line_extension_amount'])->toBe(250.0)
        ->and($payload['tax_exclusive_amount'])->toBe(250.0)
        ->and($payload['tax_amount'])->toBe(20.0)
        ->and($payload['tax_inclusive_amount'])->toBe(270.0)
        ->and($payload['payable_amount'])->toBe(270.0);
});

it('codes taxable and exempt lines per the MY tax-type list (R15)', function () {
    $soId = seedEInvoiceOrder();

    $payload = $this->tenant->run(fn () => app(EInvoiceBuilder::class)
        ->build(SalesOrder::with(['customer', 'items'])->find($soId))
        ->toArray());

    [$taxed, $exempt] = $payload['invoice_lines'];

    expect($taxed['tax_category_code'])->toBe('01')   // Sales Tax
        ->and($taxed['invoiced_quantity'])->toBe(2.0)
        ->and($taxed['price_amount'])->toBe(100.0)
        ->and($taxed['line_extension_amount'])->toBe(200.0)
        ->and($taxed['tax_percent'])->toBe(10.0)
        ->and($taxed['tax_amount'])->toBe(20.0)
        ->and($taxed['unit_code'])->toBe('ea')
        // SG needs Name, MY needs Description — both are emitted.
        ->and($taxed['item_name'])->toBe('Widget')
        ->and($taxed['item_description'])->toBe('Widget');

    expect($exempt['tax_category_code'])->toBe('E')   // Tax exemption
        ->and($exempt['tax_amount'])->toBe(0.0)
        ->and($exempt['tax_percent'])->toBe(0.0);

    // One TaxSubtotal per category, and they reconcile to the document total.
    $subtotals = collect($payload['tax_subtotals'])->keyBy('tax_category_code');
    expect($subtotals['01'])->toBe([
        'tax_category_code' => '01', 'taxable_amount' => 200.0, 'tax_amount' => 20.0, 'percent' => 10.0,
    ])
        ->and($subtotals['E']['taxable_amount'])->toBe(50.0)
        ->and($subtotals['E']['tax_amount'])->toBe(0.0)
        ->and(collect($payload['tax_subtotals'])->sum('tax_amount'))->toBe($payload['tax_amount']);
});

it('adapts the payload to Singapore InvoiceNow when the tenant is SG (R15)', function () {
    $soId = seedEInvoiceOrder([
        'country' => 'SG',
        'tax_type' => 'gst',
        'tax_rate' => '9',
        'default_currency' => 'SGD',
    ]);
    // The order was created under the MY seed; re-snapshot it as a 9% SGD sale.
    $this->tenant->run(fn () => SalesOrder::find($soId)->update(['currency' => 'SGD', 'tax_rate' => 9]));

    $payload = $this->tenant->run(fn () => app(EInvoiceBuilder::class)
        ->build(SalesOrder::with(['customer', 'items'])->find($soId))
        ->toArray());

    expect($payload['standard'])->toBe('invoicenow')
        ->and($payload['country_code'])->toBe('SG')
        // UNTDID 1001 commercial invoice, not LHDN's "01".
        ->and($payload['invoice_type_code'])->toBe('380');

    $seller = $payload['accounting_supplier_party'];
    expect($seller['party_identifications'][1])->toBe(['scheme_id' => 'UEN', 'id' => '202301012345'])
        // Peppol routes on the endpoint: UEN under Electronic Address Scheme 0195.
        ->and($seller['endpoint_id'])->toBe(['scheme_id' => '0195', 'id' => 'SGUEN202301012345'])
        // SG's tax scheme is GST, not the base Peppol profile's VAT.
        ->and($seller['party_tax_scheme']['tax_scheme_id'])->toBe('GST');

    // PINT SG GST categories: standard-rated vs zero-rated.
    [$taxed, $untaxed] = $payload['invoice_lines'];
    expect($taxed['tax_category_code'])->toBe('SR')
        ->and($taxed['tax_percent'])->toBe(9.0)
        ->and($untaxed['tax_category_code'])->toBe('ZR');

    expect($payload['tax_amount'])->toBe(18.0)     // 9% of 200
        ->and($payload['payable_amount'])->toBe(268.0);
});

it('declares the conversion when the invoice is not in the tax currency (R15)', function () {
    $soId = seedEInvoiceOrder(order: ['currency' => 'USD', 'exchange_rate' => 4.72]);

    $payload = $this->tenant->run(fn () => app(EInvoiceBuilder::class)
        ->build(SalesOrder::with(['customer', 'items'])->find($soId))
        ->toArray());

    expect($payload['document_currency_code'])->toBe('USD')
        ->and($payload['tax_currency_code'])->toBe('MYR')
        ->and($payload['tax_exchange_rate'])->toBe([
            'source_currency_code' => 'USD',
            'target_currency_code' => 'MYR',
            'calculation_rate' => 4.72,
        ]);
});

it('codes every line "not applicable" when the tenant charges no tax (R15)', function () {
    $soId = seedEInvoiceOrder(['tax_type' => 'none', 'tax_rate' => '0']);
    $this->tenant->run(fn () => SalesOrder::find($soId)->update(['tax_rate' => 0]));

    $payload = $this->tenant->run(fn () => app(EInvoiceBuilder::class)
        ->build(SalesOrder::with(['customer', 'items'])->find($soId))
        ->toArray());

    expect(collect($payload['invoice_lines'])->pluck('tax_category_code')->unique()->all())->toBe(['06'])
        ->and($payload['tax_amount'])->toBe(0.0)
        ->and($payload['payable_amount'])->toBe(250.0);
});

it('lands the rounding residual on the last taxed line, never an exempt one (R15)', function () {
    // Per line: round(3.33 × 10%, 2) = 0.33, so the lines sum to 0.99 — but the
    // document tax is round(9.99 × 10%, 2) = 1.00. The 0.01 residual has to land
    // somewhere, and a trailing EXEMPT line proves it isn't simply "the last line":
    // tax on a category-E line would be invalid under MyInvois.
    $soId = $this->tenant->run(function () {
        Setting::putMany('business', sellerSettings());
        $customer = Customer::create(['name' => 'Globex']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);
        $so = SalesOrder::create([
            'customer_id' => $customer->id, 'currency' => 'MYR',
            'tax_rate' => 10, 'number' => 'SO-2026-0002', 'status' => 'pending',
        ]);
        collect(range(1, 3))->each(fn () => $so->items()->create([
            'product_id' => $widget->id,
            'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea'],
            'quantity' => 1, 'unit_price' => 3.33, 'taxable' => true,
        ]));
        $so->items()->create([
            'product_id' => $widget->id,
            'product_snapshot' => ['name' => 'Freight', 'sku' => 'F-1', 'unit' => 'job'],
            'quantity' => 1, 'unit_price' => 20, 'taxable' => false,
        ]);

        return $so->id;
    });

    $payload = $this->tenant->run(fn () => app(EInvoiceBuilder::class)
        ->build(SalesOrder::with(['customer', 'items'])->find($soId))
        ->toArray());

    $lines = $payload['invoice_lines'];
    $exempt = end($lines);

    // The residual went to the third (last taxed) line, not the exempt fourth.
    expect($payload['tax_amount'])->toBe(1.0)
        ->and($lines[2]['tax_amount'])->toBe(0.34)
        ->and($exempt['tax_category_code'])->toBe('E')
        ->and($exempt['tax_amount'])->toBe(0.0);

    // And the whole document still reconciles.
    expect(round(collect($lines)->sum('tax_amount'), 2))->toBe($payload['tax_amount'])
        ->and(collect($payload['tax_subtotals'])->firstWhere('tax_category_code', 'E')['tax_amount'])
        ->toBe(0.0)
        ->and(round($payload['tax_exclusive_amount'] + $payload['tax_amount'], 2))
        ->toBe($payload['tax_inclusive_amount']);
});

it('downloads the e-invoice JSON as an attachment (R15)', function () {
    $soId = seedEInvoiceOrder();
    loginAsAcmeUser();

    $response = $this->get("/acme/sales-orders/{$soId}/e-invoice")
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=e-invoice-SO-2026-0001.json');

    expect($response->json('standard'))->toBe('myinvois')
        ->and($response->json('payable_amount'))->toBe(270.0);
});

it('forbids the e-invoice download without the sales-orders view permission (R15)', function () {
    $soId = seedEInvoiceOrder();

    loginAsAcmeMember([]);

    $this->get("/acme/sales-orders/{$soId}/e-invoice")->assertForbidden();
});

it('redirects a guest from the e-invoice download to the tenant login (R15)', function () {
    $soId = seedEInvoiceOrder();

    $this->get("/acme/sales-orders/{$soId}/e-invoice")
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('404s the e-invoice download for an order that does not exist (R15)', function () {
    seedEInvoiceOrder();
    loginAsAcmeUser();

    $this->get('/acme/sales-orders/999999/e-invoice')->assertNotFound();
});

it('404s the e-invoice download for a deleted order (R15)', function () {
    $soId = seedEInvoiceOrder();
    $this->tenant->run(fn () => SalesOrder::find($soId)->delete());

    loginAsAcmeUser();

    $this->get("/acme/sales-orders/{$soId}/e-invoice")->assertNotFound();
});

it('builds a zero-total payload for an order with no lines, without dividing by zero (R15)', function () {
    $soId = seedEInvoiceOrder();
    $this->tenant->run(fn () => SalesOrder::find($soId)->items()->delete());

    loginAsAcmeUser();

    $response = $this->get("/acme/sales-orders/{$soId}/e-invoice")->assertOk();

    // Real zeros, not NaN/null — an empty invoice is still a valid document.
    expect($response->json('line_extension_amount'))->toBe(0.0)
        ->and($response->json('tax_exclusive_amount'))->toBe(0.0)
        ->and($response->json('tax_amount'))->toBe(0.0)
        ->and($response->json('tax_inclusive_amount'))->toBe(0.0)
        ->and($response->json('payable_amount'))->toBe(0.0)
        ->and($response->json('invoice_lines'))->toBe([])
        ->and($response->json('tax_subtotals'))->toBe([]);
});

it('allows the e-invoice download with the sales-orders view permission (R15)', function () {
    $soId = seedEInvoiceOrder();

    loginAsAcmeMember(['sales-orders.view']);

    $this->get("/acme/sales-orders/{$soId}/e-invoice")->assertOk();
});

it('reports e-invoice readiness on the sales order page (R15)', function () {
    $soId = seedEInvoiceOrder();
    loginAsAcmeUser();

    // Fully-identified seller + buyer + numbered document → every check passes.
    $this->get("/acme/sales-orders/{$soId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('eInvoice.ready', true)
            ->where('eInvoice.passed', 10)
            ->where('eInvoice.total', 10)
        );
});

it('flags exactly what is missing when the order is not e-invoice ready (R15)', function () {
    // No seller TIN, no tax type, and a buyer with no tax identity at all.
    $soId = seedEInvoiceOrder(
        ['tin' => '', 'tax_type' => 'none', 'tax_rate' => '0'],
        ['tin' => null, 'registration_no' => null, 'address' => null, 'country_code' => null],
    );
    loginAsAcmeUser();

    $checks = $this->get("/acme/sales-orders/{$soId}")
        ->assertOk()
        ->viewData('page')['props']['eInvoice']['checks'];

    $failed = collect($checks)->reject(fn (array $c) => $c['passed'])->pluck('key')->all();

    // tax_registration is NOT expected: with tax_type "none" there is none to give.
    expect($failed)->toBe([
        'seller_tin', 'tax_type', 'buyer_tin', 'buyer_registration', 'buyer_address',
    ]);
});

it('flags a missing SST number only once the tenant charges tax (R15)', function () {
    $soId = seedEInvoiceOrder(['tax_registration_no' => '']);

    $readiness = $this->tenant->run(fn () => EInvoiceReadinessData::forSalesOrder(
        SalesOrder::with('customer')->find($soId),
        app(BusinessSettings::class),
    )->toArray());

    $check = collect($readiness['checks'])->firstWhere('key', 'tax_registration');

    // Charging 10% SST with no SST registration number is not filable.
    expect($check['passed'])->toBeFalse()
        ->and($check['label'])->toBe('Your SST registration no.')
        ->and($readiness['ready'])->toBeFalse();
});

it('never reports a cancelled order as e-invoice ready (R15)', function () {
    $soId = seedEInvoiceOrder(order: ['status' => 'cancelled']);

    $readiness = $this->tenant->run(fn () => EInvoiceReadinessData::forSalesOrder(
        SalesOrder::with('customer')->find($soId),
        app(BusinessSettings::class),
    )->toArray());

    $failed = collect($readiness['checks'])->reject(fn (array $c) => $c['passed'])->pluck('key')->all();

    expect($failed)->toBe(['not_cancelled'])
        ->and($readiness['ready'])->toBeFalse();
});

it('falls back to the workspace name when no legal name is set (R15)', function () {
    // "Leave blank to use the workspace name" — the seller name is mandatory in
    // both standards, so it must never export as an empty string.
    $soId = seedEInvoiceOrder(['legal_name' => '']);

    $payload = $this->tenant->run(fn () => app(EInvoiceBuilder::class)
        ->build(SalesOrder::with(['customer', 'items'])->find($soId))
        ->toArray());

    expect($payload['accounting_supplier_party']['registration_name'])->toBe('Acme');
});

it('leaves a buyer with no country blank rather than assuming the tenant (R15)', function () {
    $soId = seedEInvoiceOrder(customer: ['country_code' => null]);

    $payload = $this->tenant->run(fn () => app(EInvoiceBuilder::class)
        ->build(SalesOrder::with(['customer', 'items'])->find($soId))
        ->toArray());

    // Defaulting to MY would silently turn an export sale into a domestic one.
    expect($payload['accounting_customer_party']['postal_address']['country_identification_code'])
        ->toBeNull();
});

it('exports an order whose customer was deleted, with a null buyer (R15)', function () {
    $soId = seedEInvoiceOrder();
    $this->tenant->run(fn () => Customer::query()->delete());

    $payload = $this->tenant->run(fn () => app(EInvoiceBuilder::class)
        ->build(SalesOrder::with(['customer', 'items'])->find($soId))
        ->toArray());

    expect($payload['accounting_customer_party'])->toBeNull()
        // The seller and the sale itself are still fully described.
        ->and($payload['accounting_supplier_party']['registration_name'])->toBe('Acme Manufacturing Sdn Bhd')
        ->and($payload['payable_amount'])->toBe(270.0);
});

it('sanitises a document number that would break the download header (R15)', function () {
    // Slashes are ordinary in MY/SG numbering; the quote is the hostile part.
    $soId = seedEInvoiceOrder(order: ['number' => 'INV/2026"001']);
    loginAsAcmeUser();

    $header = $this->get("/acme/sales-orders/{$soId}/e-invoice")
        ->assertOk()
        ->headers->get('content-disposition');

    // Neither character survives into the header, so nothing can terminate the
    // filename early or inject a second disposition parameter.
    expect($header)->toBe('attachment; filename=e-invoice-INV-2026-001.json')
        ->and($header)->not->toContain('"')
        ->and($header)->not->toContain('/');
});

it('keeps every monetary amount at two decimals (R15)', function () {
    // 3 × 10.10 + 1 × 5.05 = 35.35, which is not exactly representable in binary
    // floating point — an unrounded sum would serialise as 35.349999999999994.
    $soId = $this->tenant->run(function () {
        Setting::putMany('business', sellerSettings());
        $customer = Customer::create(['name' => 'Globex']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);
        $so = SalesOrder::create(['customer_id' => $customer->id, 'currency' => 'MYR',
            'tax_rate' => 10, 'number' => 'SO-2026-0003', 'status' => 'pending']);
        $so->items()->create(['product_id' => $widget->id,
            'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea'],
            'quantity' => 3, 'unit_price' => 10.10, 'taxable' => true]);
        $so->items()->create(['product_id' => $widget->id,
            'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea'],
            'quantity' => 1, 'unit_price' => 5.05, 'taxable' => true]);

        return $so->id;
    });

    $payload = $this->tenant->run(fn () => app(EInvoiceBuilder::class)
        ->build(SalesOrder::with(['customer', 'items'])->find($soId))
        ->toArray());

    $amounts = [
        $payload['line_extension_amount'], $payload['tax_exclusive_amount'],
        $payload['tax_amount'], $payload['tax_inclusive_amount'], $payload['payable_amount'],
        ...collect($payload['invoice_lines'])->pluck('line_extension_amount')->all(),
        ...collect($payload['tax_subtotals'])->pluck('taxable_amount')->all(),
    ];
    foreach ($amounts as $amount) {
        expect($amount)->toBe(round($amount, 2));
    }

    // PINT BR-S-08: the category's taxable amount equals the sum of its lines.
    expect($payload['tax_subtotals'][0]['taxable_amount'])->toBe($payload['tax_exclusive_amount'])
        ->and($payload['line_extension_amount'])->toBe(35.35);
});

it('converts the tax into the tax-accounting currency for a foreign order (R15)', function () {
    $soId = seedEInvoiceOrder(order: ['currency' => 'USD', 'exchange_rate' => 4.72]);

    $payload = $this->tenant->run(fn () => app(EInvoiceBuilder::class)
        ->build(SalesOrder::with(['customer', 'items'])->find($soId))
        ->toArray());

    // 20 USD of tax is RM94.40 — the figure MY must file and SG's second TaxTotal.
    expect($payload['tax_amount'])->toBe(20.0)
        ->and($payload['tax_currency_code'])->toBe('MYR')
        ->and($payload['tax_amount_in_tax_currency'])->toBe(94.4);
});

it('carries both parties\' contact numbers, which MyInvois requires (R15)', function () {
    $soId = seedEInvoiceOrder(['phone' => '+60 3-7890 1234'], ['phone' => '+65 6123 4567']);

    $payload = $this->tenant->run(fn () => app(EInvoiceBuilder::class)
        ->build(SalesOrder::with(['customer', 'items'])->find($soId))
        ->toArray());

    expect($payload['accounting_supplier_party']['telephone'])->toBe('+60 3-7890 1234')
        ->and($payload['accounting_customer_party']['telephone'])->toBe('+65 6123 4567');
});

it('labels the registration check UEN for a Singapore tenant (R15)', function () {
    $soId = seedEInvoiceOrder(['country' => 'SG', 'tax_type' => 'gst', 'tax_rate' => '9']);

    $readiness = $this->tenant->run(fn () => EInvoiceReadinessData::forSalesOrder(
        SalesOrder::with('customer')->find($soId),
        app(BusinessSettings::class),
    )->toArray());

    $labels = collect($readiness['checks'])->keyBy('key');

    expect($labels['seller_registration']['label'])->toBe('Your UEN')
        ->and($labels['buyer_registration']['label'])->toBe("Customer's UEN");
});
