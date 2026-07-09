<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantStorageController
{
    /**
     * The active tenant's PRIVATE disk (storage/app/private), suffixed per-tenant
     * by FilesystemTenancyBootstrapper. This route is the ONLY way to read these
     * files — it runs behind auth:web, so uploads stay private (never symlinked).
     */
    private const DISK = 'local';

    /**
     * Stream a file from the active tenant's private disk. The disk root is
     * suffixed per-tenant (InitializeTenancyByPath has already run for this
     * route), so this only ever serves the current tenant's own uploads. Path
     * traversal is rejected; missing files 404.
     *
     * Note: InitializeTenancyByPath "forgets" the {tenant} route parameter once
     * tenancy is resolved (see routes/tenant.php), so only {path} is injected here.
     */
    public function __invoke(string $path): StreamedResponse
    {
        abort_if(str_contains($path, '..'), 404);
        abort_unless(Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->response($path);
    }
}
