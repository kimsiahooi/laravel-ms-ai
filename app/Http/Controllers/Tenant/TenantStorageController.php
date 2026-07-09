<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantStorageController
{
    /**
     * Stream a file from the ACTIVE tenant's public disk. The disk root is
     * suffixed per-tenant by FilesystemTenancyBootstrapper (InitializeTenancyByPath
     * has already run for this route), so this only ever serves the current
     * tenant's own uploads. Path traversal is rejected; missing files 404.
     *
     * Note: InitializeTenancyByPath "forgets" the {tenant} route parameter once
     * tenancy is resolved (see routes/tenant.php), so only {path} is injected here.
     */
    public function __invoke(string $path): StreamedResponse
    {
        abort_if(str_contains($path, '..'), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }
}
