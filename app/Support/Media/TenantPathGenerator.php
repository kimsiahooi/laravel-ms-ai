<?php

declare(strict_types=1);

namespace App\Support\Media;

use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Namespaces every media file under the current tenant's slug on the shared,
 * private `assets` disk — `assets/{slug}/{media-id}/{file}`. Without the slug
 * prefix, per-tenant-DB media ids (which restart at 1 in each tenant database)
 * would collide on the shared disk. The `{slug}` segment is also the folder
 * removed when a tenant is torn down (see App\Jobs\DeleteTenantAssets).
 */
final class TenantPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->tenantPrefix().$media->getKey().'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive/';
    }

    /**
     * The current tenant's slug as a path segment. Media is only ever read or
     * written inside a tenant request, so a missing tenant is a bug — fail loud
     * rather than leak files into a shared/root folder.
     */
    private function tenantPrefix(): string
    {
        $slug = tenant('id');

        if (! is_string($slug) || $slug === '') {
            throw new RuntimeException(
                'Media path requested outside a tenant context.',
            );
        }

        return $slug.'/';
    }
}
