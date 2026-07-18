<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithTenantAssets;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Settings\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders + saves a settings category through its code-defined schema. The category is
 * resolved from SettingsRegistry, so one controller serves every settings group. Values
 * live in the `settings` KV table; file fields (e.g. the logo) are stored on the private
 * tenant asset disk and their path written back into the KV store.
 */
class SettingsController
{
    use InteractsWithTenantAssets;
    use RespondsWithToast;

    public function __construct(private readonly SettingsRegistry $registry) {}

    public function edit(string $category): Response
    {
        $provider = $this->registry->resolve($category);

        return Inertia::render('tenant/settings/index', [
            'category' => $category,
            'schema' => $provider->schema(),
            'values' => $provider->values(),
        ]);
    }

    public function update(Request $request, string $category): RedirectResponse
    {
        $provider = $this->registry->resolve($category);

        $validated = $request->validate([...$provider->rules(), ...$provider->fileRules()]);

        foreach ($provider->fileFields() as $field) {
            // A newly uploaded file wins over the remove flag.
            if ($request->hasFile($field->key)) {
                $this->deleteAsset($provider->rawValue($field->key));
                $provider->putRaw($field->key, $this->storeAsset($request->file($field->key), $category));
            } elseif ($request->boolean('remove_'.$field->key)) {
                $this->deleteAsset($provider->rawValue($field->key));
                $provider->putRaw($field->key, null);
            }
        }

        // store() skips file fields + unknown keys (the remove_* flags), so passing the
        // full validated set is safe.
        $provider->store($validated);

        $this->toast('Settings saved.');

        return back();
    }
}
