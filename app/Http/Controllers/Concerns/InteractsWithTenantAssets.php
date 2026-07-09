<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Store / read / delete private per-tenant uploads (product images, and future
 * avatars, logos, …). Files live at `assets/{tenant-slug}/{directory}/{hash}` on
 * the central, private `assets` disk. The persisted/returned path is slug-free
 * (`{directory}/{hash}`) — the active tenant's slug is re-derived on read, so it
 * never appears twice in the served URL and can never be user-supplied. Served
 * only through the auth-gated tenant.storage route (see TenantStorageController);
 * never the `public` disk, so uploads can't be exposed by `storage:link`.
 */
trait InteractsWithTenantAssets
{
    protected function assetDisk(): string
    {
        return 'assets';
    }

    /**
     * Store an upload under `{tenant}/{directory}` and return the slug-free path
     * (`{directory}/{hash}`) to persist.
     */
    protected function storeAsset(UploadedFile $file, string $directory): string
    {
        $stored = $file->store(tenant('id').'/'.$directory, $this->assetDisk());

        return Str::after($stored, tenant('id').'/');
    }

    /** Delete a previously stored (slug-free) asset path, if one is set. */
    protected function deleteAsset(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk($this->assetDisk())->delete($this->scopeAsset($path));
        }
    }

    /**
     * Scope a slug-free path to the active tenant's asset folder — the tenant
     * slug always comes from tenancy, never user input, so one tenant can never
     * reach another's files.
     */
    protected function scopeAsset(string $path): string
    {
        return tenant('id').'/'.$path;
    }
}
