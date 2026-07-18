<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithTenantAssets;
use App\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a file-typed setting (e.g. the business logo) behind auth:web. Served at an
 * extension-less URL (GET settings/{category}/file/{key}) so nginx routes it to Laravel
 * instead of serving it statically (see ProductImageController for the rationale). The
 * stored path is read-only and scoped to the active tenant, so one tenant can't reach
 * another's file.
 */
class SettingsFileController
{
    use InteractsWithTenantAssets;

    public function __construct(private readonly SettingsRegistry $registry) {}

    public function __invoke(string $category, string $key): StreamedResponse
    {
        $provider = $this->registry->resolve($category);

        // Only ever serve declared file fields — never a non-file key whose value is
        // user-entered text (which must not be treated as a storage path).
        abort_unless($provider->isFileField($key), 404);

        $path = $provider->rawValue($key);

        abort_if($path === null || $path === '', 404);

        $scoped = $this->scopeAsset($path);

        abort_unless(Storage::disk($this->assetDisk())->exists($scoped), 404);

        return Storage::disk($this->assetDisk())->response($scoped);
    }
}
