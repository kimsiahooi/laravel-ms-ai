<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithTenantAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantStorageController
{
    use InteractsWithTenantAssets;

    /**
     * Stream a file from the active tenant's asset folder. The slug-free path
     * (e.g. products/{hash}.png) arrives as a `?path=` query param so the request
     * URL never ends in a static file extension — see routes/tenant.php for why.
     * scopeAsset() prepends the active tenant's slug from tenancy (never from user
     * input), so the lookup is always within assets/{slug}/… and one tenant can't
     * reach another's files. Path traversal is rejected; missing files 404.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $path = (string) $request->query('path', '');

        abort_if($path === '' || str_contains($path, '..'), 404);

        $path = $this->scopeAsset($path);

        abort_unless(Storage::disk($this->assetDisk())->exists($path), 404);

        return Storage::disk($this->assetDisk())->response($path);
    }
}
