<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageController
{
    /**
     * Stream a product's image (behind auth:web). Served at
     * GET /{tenant}/products/{product}/image — the URL ends in `image`, not a
     * file extension, so nginx routes it to Laravel instead of trying to serve
     * it as a static file (see routes/tenant.php). Route-model binding resolves
     * {product} in the active tenant's DB, so one tenant can't reach another's;
     * the media file lives under the tenant slug on the private `assets` disk.
     */
    public function __invoke(Product $product): StreamedResponse
    {
        $media = $product->getFirstMedia('image');

        abort_if($media === null, 404);

        $path = $media->getPathRelativeToRoot();

        abort_unless(Storage::disk($media->disk)->exists($path), 404);

        return Storage::disk($media->disk)->response($path);
    }
}
