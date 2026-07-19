<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/**
 * Removes a torn-down tenant's asset folder — `storage/assets/{slug}/` — when the
 * tenant is force-deleted (wired onto the TenantDeleted pipeline alongside the DB
 * drop). The `assets` disk is central + un-suffixed, so every one of a tenant's
 * media files lives under the single `{slug}` directory (see TenantPathGenerator);
 * deleting it reclaims them all. We keep only the slug (not the model) so the job
 * stays safe to serialise even though its tenant no longer exists.
 */
class DeleteTenantAssets implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    private readonly string $slug;

    public function __construct(Tenant $tenant)
    {
        $this->slug = (string) $tenant->getKey();
    }

    public function handle(): void
    {
        Storage::disk('assets')->deleteDirectory($this->slug);
    }
}
