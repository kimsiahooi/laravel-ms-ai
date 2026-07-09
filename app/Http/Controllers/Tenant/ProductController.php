<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Requests\Tenant\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController
{
    /** @var array<int, int> */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    // Private, central disk. Product images live at assets/{tenant-slug}/... and
    // are served only through the auth-gated tenant.storage route — never the
    // `public` disk, so they can't be exposed by `storage:link`.
    private const IMAGE_DISK = 'assets';

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = (int) $request->integer('per_page', 10);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 10;
        }

        $products = Product::query()
            ->with(['category', 'supplier'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $group) use ($search): void {
                    $group->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
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
            $data['image'] = $request->file('image')->store(tenant('id'), self::IMAGE_DISK);
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
            $this->deleteImage($product);
            $data['image'] = $request->file('image')->store(tenant('id'), self::IMAGE_DISK);
        } elseif ($removeImage) {
            $this->deleteImage($product);
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

    private function deleteImage(Product $product): void
    {
        if ($product->image !== null) {
            Storage::disk(self::IMAGE_DISK)->delete($product->image);
        }
    }
}
