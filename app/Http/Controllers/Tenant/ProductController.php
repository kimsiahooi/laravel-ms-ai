<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\OptionData;
use App\Data\ProductData;
use App\Http\Controllers\Concerns\InteractsWithTenantAssets;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\BomRequest;
use App\Http\Requests\Tenant\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController
{
    use InteractsWithTenantAssets;
    use ResolvesPerPage;
    use RespondsWithToast;

    // Product images live at assets/{tenant-slug}/products/{hash}; the DB stores
    // the slug-free `products/{hash}`. See InteractsWithTenantAssets.
    private const IMAGE_DIRECTORY = 'products';

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = $this->perPage($request);

        $products = Product::query()
            ->with(['category', 'supplier', 'bomItems.rawMaterial'])
            ->search($search)
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Product $product): ProductData => ProductData::from($product));

        return Inertia::render('tenant/products/index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'categories' => OptionData::collect(Category::orderBy('name')->get(['id', 'name'])),
            'suppliers' => OptionData::collect(Supplier::orderBy('name')->get(['id', 'name'])),
            'rawMaterials' => OptionData::collect(RawMaterial::orderBy('name')->get(['id', 'name'])),
        ]);
    }

    /**
     * Replace a product's BOM (the raw materials + per-unit quantity needed
     * to make one unit). A production order snapshots this at creation, so
     * editing it does not touch existing orders.
     */
    public function updateBom(BomRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $product->bomItems()->delete();

            foreach ($request->array('items') as $item) {
                $product->bomItems()->create([
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        });

        $this->toast('BOM saved.');

        return back();
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

        $this->toast('Product created.');

        return back();
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

        $this->toast('Product updated.');

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Soft delete; the image file is kept so a restore stays intact.
        $product->delete();

        $this->toast('Product deleted.');

        return back();
    }
}
