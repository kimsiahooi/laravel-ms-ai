<?php

use App\Actions\ProvisionTenant;
use App\Models\Product;

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
