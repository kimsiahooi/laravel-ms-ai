<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantStorageController
{
    /**
     * Private, central disk holding tenant uploads at assets/{slug}/... . This
     * route (behind auth:web) is the ONLY way to read these files, so they stay
     * private — never symlinked/public.
     */
    private const DISK = 'assets';

    /**
     * Stream a file from the active tenant's asset folder. Because the disk is
     * central (shared across tenants, namespaced by slug), the path MUST start
     * with the active tenant's slug — this blocks one tenant from reading
     * another's files. Path traversal is rejected; missing files 404.
     *
     * Note: InitializeTenancyByPath "forgets" the {tenant} route parameter once
     * tenancy is resolved (see routes/tenant.php), so only {path} is injected here.
     */
    public function __invoke(string $path): StreamedResponse
    {
        abort_if(str_contains($path, '..'), 404);
        abort_unless(str_starts_with($path, tenant('id').'/'), 404);
        abort_unless(Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->response($path);
    }
}
