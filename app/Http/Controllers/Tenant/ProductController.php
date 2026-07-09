<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithTenantAssets;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Requests\Tenant\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController
{
    use InteractsWithTenantAssets;
    use ResolvesPerPage;

    // Product images live at assets/{tenant-slug}/products/{hash}; the DB stores
    // the slug-free `products/{hash}`. See InteractsWithTenantAssets.
    private const IMAGE_DIRECTORY = 'products';

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = $this->perPage($request);

        $products = Product::query()
            ->with(['category', 'supplier'])
            ->search($search)
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'description' => $product->description,
                'image_url' => $product->image_url,
                'category_id' => $product->category_id,
                'supplier_id' => $product->supplier_id,
                'category' => $product->category?->name,
                'supplier' => $product->supplier?->name,
                'min_stock' => $product->min_stock,
                'unit' => $product->unit,
                'created_at' => $product->created_at,
            ]);

        return Inertia::render('tenant/products/index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['remove_image']);

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeAsset($request->file('image'), self::IMAGE_DIRECTORY);
        } else {
            unset($data['image']);
        }

        Product::create($data);

        return back()->with('success', 'Product created.');
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $removeImage = (bool) ($data['remove_image'] ?? false);
        unset($data['remove_image']);

        // A newly uploaded file takes precedence over a remove_image flag.
        if ($request->hasFile('image')) {
            $this->deleteAsset($product->image);
            $data['image'] = $this->storeAsset($request->file('image'), self::IMAGE_DIRECTORY);
        } elseif ($removeImage) {
            $this->deleteAsset($product->image);
            $data['image'] = null;
        } else {
            unset($data['image']);
        }

        $product->update($data);

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Soft delete; the image file is kept so a restore stays intact.
        $product->delete();

        return back()->with('success', 'Product deleted.');
    }
}
