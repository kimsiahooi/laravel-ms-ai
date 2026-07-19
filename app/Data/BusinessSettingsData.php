<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The typed subset of the business settings shown on document headers (shared to every
 * tenant page as `business`). The logo is exposed as `logo_url` — the content-addressed
 * media route URL (or null), which carries the media id so it changes on every re-upload
 * and a header never shows a stale logo; the raw storage path is never exposed and the
 * file streams through the auth-gated media route. #[TypeScript] emits
 * App.Data.BusinessSettingsData.
 */
#[TypeScript]
class BusinessSettingsData extends Data
{
    public function __construct(
        public ?string $legal_name,
        public ?string $registration_no,
        public ?string $address,
        public string $tax_type,
        public ?string $tax_registration_no,
        public ?string $logo_url,
    ) {}
}
