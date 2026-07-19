<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\StreamsMedia;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams any tenant media file by id (behind auth:web), served at an extension-less
 * URL: GET /{tenant}/media/{media}. Extension-less so nginx routes it to Laravel instead
 * of serving it statically (see the route file for the rationale).
 *
 * The id is the version: URLs are content-addressed, so a re-upload makes a new media row
 * (the collections are singleFile → old row + file deleted, new row → new id) → a new URL.
 * A stored URL therefore always points at the current file, and the browser fetches fresh
 * bytes with no reload. Route-model binding resolves {media} in the ACTIVE tenant's DB, so
 * one tenant can't reach another's (separate databases), and a deleted id 404s — accessing
 * an old /media/{id} shows nothing. Files live under the tenant slug on the private
 * `assets` disk.
 */
class MediaController
{
    use StreamsMedia;

    public function __invoke(Request $request, Media $media): StreamedResponse
    {
        return $this->streamMedia($request, $media);
    }
}
