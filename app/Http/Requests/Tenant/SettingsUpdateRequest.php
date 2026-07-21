<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Settings\SettingsCategory;
use App\Settings\SettingsRegistry;

/**
 * Validates a settings save through the category's code-defined schema. The rules are
 * the same per-field `Field::rules` that drive the form (resolved via SettingsRegistry
 * from the {category} route param), so one request class serves every settings group and
 * the form + validation never drift. Unknown category 404s via the registry.
 */
class SettingsUpdateRequest extends TenantFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $provider = $this->provider();

        return [...$provider->rules(), ...$provider->fileRules()];
    }

    /** The settings provider for the current {category} route param. */
    public function provider(): SettingsCategory
    {
        return app(SettingsRegistry::class)->resolve((string) $this->route('category'));
    }
}
