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
function seedRecipeFixture(): array
{
    return test()->tenant->run(fn () => [
        'product' => Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea'])->id,
        'steel' => RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg'])->id,
        'bolt' => RawMaterial::create(['name' => 'Bolt', 'sku' => 'B-1', 'unit' => 'ea'])->id,
    ]);
}

it('redirects a guest saving a recipe to the tenant login', function () {
    ['product' => $product] = seedRecipeFixture();

    $this->put("/acme/products/{$product}/recipe", ['items' => []])
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('sets a product recipe', function () {
    ['product' => $product, 'steel' => $steel, 'bolt' => $bolt] = seedRecipeFixture();

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$product}/recipe", [
            'items' => [
                ['raw_material_id' => $steel, 'quantity' => 2],
                ['raw_material_id' => $bolt, 'quantity' => 4],
            ],
        ])
        ->assertRedirect('/acme/products')
        ->assertToast('Recipe saved.');

    $this->tenant->run(function () use ($product, $steel) {
        $recipe = Product::find($product)->recipeItems;
        expect($recipe)->toHaveCount(2)
            ->and((float) $recipe->firstWhere('raw_material_id', $steel)->quantity)->toBe(2.0);
    });
});

it('replaces the recipe on re-save and clears it with an empty array', function () {
    ['product' => $product, 'steel' => $steel, 'bolt' => $bolt] = seedRecipeFixture();

    loginAsAcmeUser();

    $this->put("/acme/products/{$product}/recipe", [
        'items' => [['raw_material_id' => $steel, 'quantity' => 2]],
    ]);

    // Re-save with a different single line.
    $this->put("/acme/products/{$product}/recipe", [
        'items' => [['raw_material_id' => $bolt, 'quantity' => 5]],
    ])->assertSessionHasNoErrors();

    $this->tenant->run(function () use ($product, $bolt) {
        $recipe = Product::find($product)->recipeItems;
        expect($recipe)->toHaveCount(1)
            ->and($recipe->first()->raw_material_id)->toBe($bolt);
    });

    // Empty items clears the recipe.
    $this->put("/acme/products/{$product}/recipe", ['items' => []])
        ->assertSessionHasNoErrors();

    $this->tenant->run(fn () => expect(Product::find($product)->recipeItems)->toHaveCount(0));
});

it('rejects duplicate raw materials in a recipe', function () {
    ['product' => $product, 'steel' => $steel] = seedRecipeFixture();

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$product}/recipe", [
            'items' => [
                ['raw_material_id' => $steel, 'quantity' => 2],
                ['raw_material_id' => $steel, 'quantity' => 3],
            ],
        ])
        ->assertRedirect('/acme/products')
        ->assertInvalid('items.1.raw_material_id');
});

it('rejects a non-positive recipe quantity', function () {
    ['product' => $product, 'steel' => $steel] = seedRecipeFixture();

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$product}/recipe", [
            'items' => [['raw_material_id' => $steel, 'quantity' => 0]],
        ])
        ->assertRedirect('/acme/products')
        ->assertInvalid('items.0.quantity');
});

it('includes each product recipe in the products index', function () {
    ['product' => $product, 'steel' => $steel] = seedRecipeFixture();

    loginAsAcmeUser();

    $this->put("/acme/products/{$product}/recipe", [
        'items' => [['raw_material_id' => $steel, 'quantity' => 2]],
    ]);

    $this->get('/acme/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/index')
            ->has('products.data.0.recipe', 1)
            ->where('products.data.0.recipe.0.name', 'Steel')
        );
});
