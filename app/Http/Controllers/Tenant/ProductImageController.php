<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\StreamsMedia;
use App\Models\Product;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageController
{
    use StreamsMedia;

    /**
     * Stream a product's image (behind auth:web). Served at
     * GET /{tenant}/products/{product}/image — the URL ends in `image`, not a
     * file extension, so nginx routes it to Laravel instead of trying to serve
     * it as a static file (see routes/tenant.php). Route-model binding resolves
     * {product} in the active tenant's DB, so one tenant can't reach another's;
     * the media file lives under the tenant slug on the private `assets` disk.
     */
    public function __invoke(Request $request, Product $product): StreamedResponse
    {
        $media = $product->getFirstMedia('image');

        abort_if($media === null, 404);

        return $this->streamMedia($request, $media);
    }
}
