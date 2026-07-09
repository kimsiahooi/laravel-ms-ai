<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithTenantAssets;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantStorageController
{
    use InteractsWithTenantAssets;

    /**
     * Stream a file from the active tenant's asset folder. The URL path is
     * slug-free (e.g. products/{hash}); scopeAsset() prepends the active tenant's
     * slug from tenancy — never from user input — so the lookup is always within
     * assets/{slug}/… and one tenant can't reach another's files. Path traversal
     * is rejected; missing files 404.
     *
     * Note: InitializeTenancyByPath "forgets" the {tenant} route parameter once
     * tenancy is resolved (see routes/tenant.php), so only {path} is injected here.
     */
    public function __invoke(string $path): StreamedResponse
    {
        abort_if(str_contains($path, '..'), 404);

        $path = $this->scopeAsset($path);

        abort_unless(Storage::disk($this->assetDisk())->exists($path), 404);

        return Storage::disk($this->assetDisk())->response($path);
    }
}
