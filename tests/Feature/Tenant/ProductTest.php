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

it('creates a product with category and supplier', function () {
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
        ->assertToast('Product created.');

    $this->tenant->run(function () use ($categoryId, $supplierId) {
        $product = Product::firstWhere('sku', 'P-001');
        expect($product)->not->toBeNull()
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

it('stores an uploaded image as media under the tenant slug', function () {
    Storage::fake('assets');
    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', [
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
            'image' => UploadedFile::fake()->image('widget.jpg', 200, 200),
        ])
        ->assertRedirect('/acme/products')
        ->assertToast('Product created.');

    $this->tenant->run(function () {
        $media = Product::firstWhere('sku', 'P-001')->getFirstMedia('image');

        expect($media)->not->toBeNull();
        // Files are namespaced by the tenant slug + media id: acme/{id}/{file}.
        expect(str_starts_with($media->getPathRelativeToRoot(), 'acme/'))->toBeTrue();
        Storage::disk('assets')->assertExists($media->getPathRelativeToRoot());
    });
});

it('adds an image to a product on update', function () {
    Storage::fake('assets');
    $id = $this->tenant->run(fn () => Product::create([
        'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
    ])->id);

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$id}", [
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
            'image' => UploadedFile::fake()->image('new.png', 150, 150),
        ])
        ->assertRedirect('/acme/products')
        ->assertToast('Product updated.');

    $this->tenant->run(function () use ($id) {
        $product = Product::find($id);
        $media = $product->getFirstMedia('image');
        expect($product->name)->toBe('Widget A')
            ->and($media)->not->toBeNull();
        Storage::disk('assets')->assertExists($media->getPathRelativeToRoot());
    });
});

it('removes a product image when remove_image is set', function () {
    Storage::fake('assets');
    $id = $this->tenant->run(function () {
        $product = Product::create(['name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs']);
        $product->addMedia(UploadedFile::fake()->image('existing.jpg'))->toMediaCollection('image');

        return $product->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$id}", [
            'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
            'remove_image' => '1',
        ])
        ->assertRedirect('/acme/products')
        ->assertToast('Product updated.');

    $this->tenant->run(function () use ($id) {
        expect(Product::find($id)->getFirstMedia('image'))->toBeNull();
    });
});

it('deletes the previous image file when replacing it', function () {
    Storage::fake('assets');

    $oldPath = $this->tenant->run(function () {
        $product = Product::create(['name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs']);
        $product->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('image');

        return $product->getFirstMedia('image')->getPathRelativeToRoot();
    });

    Storage::disk('assets')->assertExists($oldPath);

    $id = $this->tenant->run(fn () => Product::firstWhere('sku', 'P-001')->id);

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$id}", [
            'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
            'image' => UploadedFile::fake()->image('new.jpg'),
        ])
        ->assertRedirect('/acme/products')
        ->assertToast('Product updated.');

    $newPath = $this->tenant->run(
        fn () => Product::find($id)->getFirstMedia('image')->getPathRelativeToRoot(),
    );

    expect($newPath)->not->toBe($oldPath);
    Storage::disk('assets')->assertMissing($oldPath);
    Storage::disk('assets')->assertExists($newPath);
});

it('keeps a soft-deleted image but removes it on force-delete', function () {
    Storage::fake('assets');

    [$id, $path] = $this->tenant->run(function () {
        $product = Product::create(['name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs']);
        $product->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('image');

        return [$product->id, $product->getFirstMedia('image')->getPathRelativeToRoot()];
    });

    loginAsAcmeUser();

    // Soft delete (the route) keeps the media so a restore stays intact.
    $this->from('/acme/products')->delete("/acme/products/{$id}")
        ->assertRedirect('/acme/products');
    Storage::disk('assets')->assertExists($path);
    $this->tenant->run(
        fn () => expect(Product::withTrashed()->find($id)->getFirstMedia('image'))->not->toBeNull(),
    );

    // Force delete removes the media record + its file.
    $this->tenant->run(fn () => Product::withTrashed()->find($id)->forceDelete());
    Storage::disk('assets')->assertMissing($path);
});

it('soft-deletes a product', function () {
    $id = $this->tenant->run(fn () => Product::create([
        'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
    ])->id);

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->delete("/acme/products/{$id}")
        ->assertRedirect('/acme/products')
        ->assertToast('Product deleted.');

    $this->tenant->run(function () use ($id) {
        expect(Product::find($id))->toBeNull()
            ->and(Product::withTrashed()->find($id))->not->toBeNull();
    });
});

it('serves a product image over HTTP', function () {
    Storage::fake('assets');
    loginAsAcmeUser();

    $this->from('/acme/products')->post('/acme/products', [
        'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
        'image' => UploadedFile::fake()->image('widget.jpg', 80, 80),
    ])->assertRedirect('/acme/products');

    $id = $this->tenant->run(fn () => Product::firstWhere('sku', 'P-001')->id);

    $response = $this->get("/acme/products/{$id}/image");
    $response->assertOk()
        ->assertHeader('content-type', 'image/jpeg')
        ->assertHeader('etag'); // cache validator so a re-upload is never stale

    expect($response->headers->get('cache-control'))->toContain('no-cache');
});

it('changes the image ETag when the image is replaced (no stale cache)', function () {
    Storage::fake('assets');
    $id = $this->tenant->run(function () {
        $product = Product::create(['name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs']);
        $product->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('image');

        return $product->id;
    });

    loginAsAcmeUser();

    $first = $this->get("/acme/products/{$id}/image")->headers->get('etag');

    // Replacing the image makes a new media row (singleFile) -> new id -> new ETag.
    $this->from('/acme/products')->put("/acme/products/{$id}", [
        'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
        'image' => UploadedFile::fake()->image('b.jpg'),
    ])->assertRedirect('/acme/products');

    $second = $this->get("/acme/products/{$id}/image")->headers->get('etag');

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($second)->not->toBe($first);
});

it('returns 404 for the image route when a product has no image', function () {
    Storage::fake('assets');
    $id = $this->tenant->run(fn () => Product::create([
        'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
    ])->id);

    loginAsAcmeUser();

    $this->get("/acme/products/{$id}/image")->assertNotFound();
});

it('404s the image route for a product not in the current tenant', function () {
    // Route-model binding resolves {product} in the ACTIVE tenant's DB, so a
    // product id that isn't acme's (e.g. another tenant's) can't be reached.
    loginAsAcmeUser();

    $this->get('/acme/products/999999/image')->assertNotFound();
});
