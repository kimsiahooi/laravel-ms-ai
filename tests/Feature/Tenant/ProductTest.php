<?php

use App\Actions\ProvisionTenant;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

it('creates a product with category, supplier and defaults min_stock to 0', function () {
    [$categoryId, $supplierId] = $this->tenant->run(function () {
        return [
            Category::create(['name' => 'Widgets'])->id,
            Supplier::create(['name' => 'Acme Supply'])->id,
        ];
    });

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', [
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
            'category_id' => $categoryId, 'supplier_id' => $supplierId,
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($categoryId, $supplierId) {
        $product = Product::firstWhere('sku', 'P-001');
        expect($product)->not->toBeNull()
            ->and($product->min_stock)->toBe(0)
            ->and($product->category_id)->toBe($categoryId)
            ->and($product->supplier_id)->toBe($supplierId)
            ->and($product->image)->toBeNull();
    });
});

it('requires name, sku and unit', function () {
    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', [])
        ->assertRedirect('/acme/products')
        ->assertSessionHasErrors(['name', 'sku', 'unit']);
});

it('rejects a duplicate sku', function () {
    $this->tenant->run(fn () => Product::create([
        'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
    ]));

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', ['name' => 'Other', 'sku' => 'P-001', 'unit' => 'pcs'])
        ->assertRedirect('/acme/products')
        ->assertSessionHasErrors('sku');
});

it('rejects a trashed category or supplier', function () {
    $categoryId = $this->tenant->run(function () {
        $category = Category::create(['name' => 'Widgets']);
        $category->delete();

        return $category->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', [
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
            'category_id' => $categoryId,
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHasErrors('category_id');
});

it('rejects a non-integer min_stock', function () {
    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', [
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
            'min_stock' => '1.5',
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHasErrors('min_stock');
});

it('stores an uploaded image on the tenant public disk', function () {
    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', [
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
            'image' => UploadedFile::fake()->image('widget.jpg', 200, 200),
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHas('success');

    $this->tenant->run(function () {
        $product = Product::firstWhere('sku', 'P-001');
        expect($product->image)->not->toBeNull()
            ->and(str_starts_with($product->image, 'products/'))->toBeTrue()
            ->and(Storage::disk('public')->exists($product->image))->toBeTrue();
    });
});

it('updates a product and replaces its image', function () {
    $id = $this->tenant->run(fn () => Product::create([
        'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
    ])->id);

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$id}", [
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs', 'min_stock' => 5,
            'image' => UploadedFile::fake()->image('new.png', 150, 150),
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        $product = Product::find($id);
        expect($product->name)->toBe('Widget A')
            ->and($product->min_stock)->toBe(5)
            ->and($product->image)->not->toBeNull()
            ->and(Storage::disk('public')->exists($product->image))->toBeTrue();
    });
});

it('removes a product image when remove_image is set', function () {
    $id = $this->tenant->run(function () {
        return Product::create([
            'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
            'image' => 'products/existing.jpg',
        ])->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$id}", [
            'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs', 'min_stock' => 0,
            'remove_image' => '1',
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(Product::find($id)->image)->toBeNull();
    });
});

it('deletes the previous image file when replacing it', function () {
    $id = $this->tenant->run(function () {
        $path = UploadedFile::fake()->image('old.jpg')->store('products', 'public');

        return Product::create([
            'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs', 'image' => $path,
        ])->id;
    });

    $oldPath = $this->tenant->run(fn () => Product::find($id)->image);

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$id}", [
            'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs', 'min_stock' => 0,
            'image' => UploadedFile::fake()->image('new.jpg'),
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id, $oldPath) {
        $product = Product::find($id);
        expect($product->image)->not->toBe($oldPath)
            ->and(Storage::disk('public')->exists($oldPath))->toBeFalse()
            ->and(Storage::disk('public')->exists($product->image))->toBeTrue();
    });
});

it('soft-deletes a product', function () {
    $id = $this->tenant->run(fn () => Product::create([
        'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
    ])->id);

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->delete("/acme/products/{$id}")
        ->assertRedirect('/acme/products')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(Product::find($id))->toBeNull()
            ->and(Product::withTrashed()->find($id))->not->toBeNull();
    });
});
