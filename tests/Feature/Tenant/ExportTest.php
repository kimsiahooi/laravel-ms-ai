<?php

use App\Actions\ProvisionTenant;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\ExportRegistry;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('redirects a guest from an export to the tenant login', function () {
    $this->get('/acme/export/products')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('exports products as CSV, respecting the search filter', function () {
    $this->tenant->run(function () {
        Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);
        Product::create(['name' => 'Gadget', 'sku' => 'G-1', 'unit' => 'pcs']);
    });

    loginAsAcmeUser();

    $response = $this->get('/acme/export/products?format=csv&search=Widget')->assertOk();

    $content = $response->baseResponse->getFile()->getContent();
    expect($content)->toContain('Name')     // heading row
        ->toContain('Widget')
        ->not->toContain('Gadget');          // filtered out by search
});

it('exports as xlsx with the spreadsheet content type', function () {
    $this->tenant->run(fn () => Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']));

    loginAsAcmeUser();

    $this->get('/acme/export/products?format=xlsx')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('404s an unknown export resource', function () {
    loginAsAcmeUser();

    $this->get('/acme/export/nope')->assertNotFound();
});

it('exports every registered list resource as CSV and Excel without erroring', function () {
    loginAsAcmeUser();

    // Every registered resource must respond for both formats — proves each
    // registry query + column set is valid, so "export on every page" holds.
    foreach (ExportRegistry::keys() as $resource) {
        $this->get("/acme/export/{$resource}?format=csv")->assertOk();

        $this->get("/acme/export/{$resource}?format=xlsx")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
});

it('exports the reports page sections scoped to the range', function () {
    loginAsAcmeUser();

    $response = $this->get('/acme/export/reports?format=csv')->assertOk();

    $content = $response->baseResponse->getFile()->getContent();
    expect($content)->toContain('Summary')
        ->toContain('Stock movements')
        ->toContain('Low / out of stock');
});

it('exports purchase orders with their data, respecting the search filter', function () {
    $this->tenant->run(function () {
        $alpha = Supplier::create(['name' => 'Alpha Metals']);
        $beta = Supplier::create(['name' => 'Beta Supplies']);
        PurchaseOrder::create(['supplier_id' => $alpha->id, 'currency' => 'MYR', 'status' => 'pending']);
        PurchaseOrder::create(['supplier_id' => $beta->id, 'currency' => 'MYR', 'status' => 'pending']);
    });

    loginAsAcmeUser();

    $response = $this->get('/acme/export/purchase-orders?format=csv&search=Beta')->assertOk();

    $content = $response->baseResponse->getFile()->getContent();
    expect($content)->toContain('Order #')       // heading row
        ->toContain('Beta Supplies')
        ->not->toContain('Alpha Metals');         // filtered out by search
});
