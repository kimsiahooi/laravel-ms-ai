<?php

use App\Actions\ProvisionTenant;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('redirects a guest from the products page to the tenant login', function () {
    $this->get('/acme/products')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('lists a tenant’s products with category/supplier options, paginated', function () {
    $this->tenant->run(function () {
        $category = Category::create(['name' => 'Widgets']);
        $supplier = Supplier::create(['name' => 'Acme Supply']);
        Product::create([
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
            'category_id' => $category->id, 'supplier_id' => $supplier->id,
        ]);
        Product::create(['name' => 'Widget B', 'sku' => 'P-002', 'unit' => 'pcs']);
    });

    loginAsAcmeUser();

    $this->get('/acme/products?per_page=10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/index')
            ->has('products.data', 2)
            ->where('products.total', 2)
            ->where('filters.per_page', 10)
            ->has('categories', 1)
            ->has('suppliers', 1)
        );
});

it('searches products by name, sku or barcode', function () {
    $this->tenant->run(function () {
        Product::create(['name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs', 'barcode' => '999']);
        Product::create(['name' => 'Gadget B', 'sku' => 'P-002', 'unit' => 'pcs']);
    });

    loginAsAcmeUser();

    $this->get('/acme/products?search=999')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'P-001')
        );
});
