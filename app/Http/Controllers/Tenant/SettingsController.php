<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\SettingsUpdateRequest;
use App\Settings\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders + saves a settings category through its code-defined schema. The category is
 * resolved from SettingsRegistry, so one controller serves every settings group. Values
 * live in the `settings` KV table; file fields (e.g. the logo) attach their upload to the
 * field's row via medialibrary (see SettingsCategory::putFile).
 */
class SettingsController
{
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

    public function update(SettingsUpdateRequest $request): RedirectResponse
    {
        $provider = $request->provider();

        $validated = $request->validated();

        foreach ($provider->fileFields() as $field) {
            // A newly uploaded file wins over the remove flag; putFile replaces
            // (and deletes) any previous upload, clearFile removes it.
            $file = $request->file($field->key);
            if ($file instanceof UploadedFile) {
                $provider->putFile($field->key, $file);
            } elseif ($request->boolean('remove_'.$field->key)) {
                $provider->clearFile($field->key);
            }
        }

        // store() skips file fields + unknown keys (the remove_* flags), so passing the
        // full validated set is safe.
        $provider->store($validated);

        $this->toast('Settings saved.');

        return back();
    }
}
