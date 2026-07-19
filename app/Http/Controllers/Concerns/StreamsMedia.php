<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The single, correct way to stream a media file from its (private) disk.
 *
 * It attaches cache validators so the browser MUST revalidate before reusing a
 * cached copy: fresh bytes when the file changed — a re-upload replaces the media
 * (new row → new id → new ETag) — and a cheap 304 when it hasn't. Without this,
 * media served from a stable URL (a product image, the settings logo) keeps
 * showing the previous file after a re-upload until a hard reload.
 *
 * All media serving must go through here (a `tests/Arch` rule forbids tenant
 * controllers from touching the Storage facade directly), so every upload — image
 * or future file — stays fresh by construction.
 */
trait StreamsMedia
{
    protected function streamMedia(Request $request, Media $media): StreamedResponse
    {
        $path = $media->getPathRelativeToRoot();

        abort_unless(Storage::disk($media->disk)->exists($path), 404);

        $response = Storage::disk($media->disk)->response($path);

        // Revalidate before reuse; the ETag/Last-Modified change on every re-upload,
        // so a stale cached copy can never survive.
        $etag = (string) $media->getKey();
        if ($media->updated_at !== null) {
            $etag .= '-'.$media->updated_at->getTimestamp();
        }

        $response->setPrivate();
        $response->setEtag($etag);
        $response->setLastModified($media->updated_at);
        $response->headers->addCacheControlDirective('no-cache');
        $response->isNotModified($request);

        return $response;
    }
}
