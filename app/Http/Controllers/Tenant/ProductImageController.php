<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithTenantAssets;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageController
{
    use InteractsWithTenantAssets;

    /**
     * Stream a product's image (behind auth:web). Served at
     * GET /{tenant}/products/{product}/image — the URL ends in `image`, not a
     * file extension, so nginx routes it to Laravel instead of trying to serve
     * it as a static file (see routes/tenant.php). Route-model binding resolves
     * {product} in the active tenant's DB, so one tenant can't reach another's;
     * scopeAsset() likewise scopes the file lookup to the active tenant.
     */
    public function __invoke(Product $product): StreamedResponse
    {
        abort_if($product->image === null, 404);

        $path = $this->scopeAsset($product->image);

        abort_unless(Storage::disk($this->assetDisk())->exists($path), 404);

        return Storage::disk($this->assetDisk())->response($path);
    }
}
