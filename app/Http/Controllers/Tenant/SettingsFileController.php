<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a file-typed setting (e.g. the business logo) behind auth:web. Served at an
 * extension-less URL (GET settings/{category}/file/{key}) so nginx routes it to Laravel
 * instead of serving it statically (see ProductImageController for the rationale). The
 * media lives under the tenant slug on the private `assets` disk, so one tenant can't
 * reach another's file.
 */
class SettingsFileController
{
    public function __construct(private readonly SettingsRegistry $registry) {}

    public function __invoke(string $category, string $key): StreamedResponse
    {
        $provider = $this->registry->resolve($category);

        // Only ever serve declared file fields — never a non-file key whose value is
        // user-entered text (which must not be treated as a storage path).
        abort_unless($provider->isFileField($key), 404);

        $media = $provider->fileMedia($key);

        abort_if($media === null, 404);

        $path = $media->getPathRelativeToRoot();

        abort_unless(Storage::disk($media->disk)->exists($path), 404);

        return Storage::disk($media->disk)->response($path);
    }
}
