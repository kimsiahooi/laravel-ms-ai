<?php

use App\Actions\ProvisionTenant;
use App\Models\Product;
use App\Models\RawMaterial;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/**
 * A product + two raw materials, in the tenant DB.
 *
 * @return array{product: int, steel: int, bolt: int}
 */
function seedBomFixture(): array
{
    return test()->tenant->run(fn () => [
        'product' => Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea'])->id,
        'steel' => RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg'])->id,
        'bolt' => RawMaterial::create(['name' => 'Bolt', 'sku' => 'B-1', 'unit' => 'ea'])->id,
    ]);
}

it('redirects a guest saving a BOM to the tenant login', function () {
    ['product' => $product] = seedBomFixture();

    $this->put("/acme/products/{$product}/bom", ['items' => []])
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('sets a product BOM', function () {
    ['product' => $product, 'steel' => $steel, 'bolt' => $bolt] = seedBomFixture();

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$product}/bom", [
            'items' => [
                ['raw_material_id' => $steel, 'quantity' => 2],
                ['raw_material_id' => $bolt, 'quantity' => 4],
            ],
        ])
        ->assertRedirect('/acme/products')
        ->assertToast('Bill of materials saved.');

    $this->tenant->run(function () use ($product, $steel) {
        $bom = Product::find($product)->bomItems;
        expect($bom)->toHaveCount(2)
            ->and((float) $bom->firstWhere('raw_material_id', $steel)->quantity)->toBe(2.0);
    });
});

it('replaces the BOM on re-save and clears it with an empty array', function () {
    ['product' => $product, 'steel' => $steel, 'bolt' => $bolt] = seedBomFixture();

    loginAsAcmeUser();

    $this->put("/acme/products/{$product}/bom", [
        'items' => [['raw_material_id' => $steel, 'quantity' => 2]],
    ]);

    // Re-save with a different single line.
    $this->put("/acme/products/{$product}/bom", [
        'items' => [['raw_material_id' => $bolt, 'quantity' => 5]],
    ])->assertSessionHasNoErrors();

    $this->tenant->run(function () use ($product, $bolt) {
        $bom = Product::find($product)->bomItems;
        expect($bom)->toHaveCount(1)
            ->and($bom->first()->raw_material_id)->toBe($bolt);
    });

    // Empty items clears the BOM.
    $this->put("/acme/products/{$product}/bom", ['items' => []])
        ->assertSessionHasNoErrors();

    $this->tenant->run(fn () => expect(Product::find($product)->bomItems)->toHaveCount(0));
});

it('rejects duplicate raw materials in a BOM', function () {
    ['product' => $product, 'steel' => $steel] = seedBomFixture();

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$product}/bom", [
            'items' => [
                ['raw_material_id' => $steel, 'quantity' => 2],
                ['raw_material_id' => $steel, 'quantity' => 3],
            ],
        ])
        ->assertRedirect('/acme/products')
        ->assertInvalid('items.1.raw_material_id');
});

it('rejects a non-positive BOM quantity', function () {
    ['product' => $product, 'steel' => $steel] = seedBomFixture();

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$product}/bom", [
            'items' => [['raw_material_id' => $steel, 'quantity' => 0]],
        ])
        ->assertRedirect('/acme/products')
        ->assertInvalid('items.0.quantity');
});

it('includes each product BOM in the products index', function () {
    ['product' => $product, 'steel' => $steel] = seedBomFixture();

    loginAsAcmeUser();

    $this->put("/acme/products/{$product}/bom", [
        'items' => [['raw_material_id' => $steel, 'quantity' => 2]],
    ]);

    $this->get('/acme/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/index')
            ->has('products.data.0.bom', 1)
            ->where('products.data.0.bom.0.name', 'Steel')
        );
});
