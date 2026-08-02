<?php

use App\Actions\ProvisionTenant;
use App\Models\Product;
use App\Models\RawMaterial;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('requires a signed-in user to resolve a scan', function () {
    $this->getJson('/acme/stock/resolve-item?code=anything')
        ->assertUnauthorized();
});

it('resolves a product by its barcode', function () {
    $id = $this->tenant->run(fn () => Product::create([
        'name' => 'Widget', 'sku' => 'W-1', 'barcode' => '9551234567890', 'unit' => 'ea',
    ])->id);

    loginAsAcmeUser();

    $this->getJson('/acme/stock/resolve-item?code=9551234567890')
        ->assertOk()
        ->assertJson([
            'value' => "product:{$id}",
            'type' => 'product',
            'name' => 'Widget',
            'sku' => 'W-1',
        ]);
});

it('resolves a product by its SKU', function () {
    $id = $this->tenant->run(fn () => Product::create([
        'name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea',
    ])->id);

    loginAsAcmeUser();

    $this->getJson('/acme/stock/resolve-item?code=W-1')
        ->assertOk()
        ->assertJson(['value' => "product:{$id}", 'type' => 'product']);
});

it('resolves a raw material by its barcode', function () {
    $id = $this->tenant->run(fn () => RawMaterial::create([
        'name' => 'Steel', 'sku' => 'S-1', 'barcode' => 'RM-BC-1', 'unit' => 'kg',
    ])->id);

    loginAsAcmeUser();

    $this->getJson('/acme/stock/resolve-item?code=RM-BC-1')
        ->assertOk()
        ->assertJson([
            'value' => "raw_material:{$id}",
            'type' => 'raw_material',
            'name' => 'Steel',
            'sku' => 'S-1',
        ]);
});

it('resolves a raw material by its SKU', function () {
    $id = $this->tenant->run(fn () => RawMaterial::create([
        'name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg',
    ])->id);

    loginAsAcmeUser();

    $this->getJson('/acme/stock/resolve-item?code=S-1')
        ->assertOk()
        ->assertJson(['value' => "raw_material:{$id}", 'type' => 'raw_material']);
});

it('prefers a matching SKU over a matching barcode across item types', function () {
    // A product's barcode and a raw material's unique SKU both equal "CLASH";
    // the SKU (the stronger, unique signal) wins.
    $rmId = $this->tenant->run(function () {
        Product::create(['name' => 'Widget', 'sku' => 'W-9', 'barcode' => 'CLASH', 'unit' => 'ea']);

        return RawMaterial::create(['name' => 'Steel', 'sku' => 'CLASH', 'unit' => 'kg'])->id;
    });

    loginAsAcmeUser();

    $this->getJson('/acme/stock/resolve-item?code=CLASH')
        ->assertOk()
        ->assertJson(['value' => "raw_material:{$rmId}", 'type' => 'raw_material']);
});

it('404s an unknown code', function () {
    loginAsAcmeUser();

    $this->getJson('/acme/stock/resolve-item?code=does-not-exist')
        ->assertNotFound();
});

it('requires a code', function () {
    loginAsAcmeUser();

    $this->getJson('/acme/stock/resolve-item')
        ->assertJsonValidationErrors('code');
});

it('stores a raw material with a barcode', function () {
    loginAsAcmeUser();

    $this->post('/acme/raw-materials', [
        'name' => 'Steel', 'sku' => 'S-1', 'barcode' => 'RM-BC-9', 'unit' => 'kg',
    ])->assertRedirect();

    $this->tenant->run(fn () => expect(RawMaterial::firstWhere('sku', 'S-1')->barcode)->toBe('RM-BC-9'));
});
