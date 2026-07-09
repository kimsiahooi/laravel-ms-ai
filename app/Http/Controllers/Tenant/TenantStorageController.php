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
     * Stream a file from the active tenant's asset folder. The URL path is
     * slug-free (e.g. products/{hash}); the active tenant's slug is prepended
     * here from tenancy — never from user input — so the lookup is always scoped
     * to assets/{slug}/… and one tenant can't reach another's files. Path
     * traversal is rejected; missing files 404.
     *
     * Note: InitializeTenancyByPath "forgets" the {tenant} route parameter once
     * tenancy is resolved (see routes/tenant.php), so only {path} is injected here.
     */
    public function __invoke(string $path): StreamedResponse
    {
        abort_if(str_contains($path, '..'), 404);

        $path = tenant('id').'/'.$path;

        abort_unless(Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->response($path);
    }
}
