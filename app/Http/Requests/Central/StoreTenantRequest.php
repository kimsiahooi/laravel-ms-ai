<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Support\ReservedSlugs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is already gated by auth:central; this is a belt-and-suspenders.
        return $this->user('central') !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                // Capped so `<db prefix><slug>` fits MySQL's 64-char database-name limit.
                'max:50',
                // Lowercase kebab only — must match the {tenant} route pattern so the
                // provisioned tenant is actually reachable by URL.
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(ReservedSlugs::LIST),
                Rule::unique('tenants', 'id'),
            ],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
            // Optional; when true, seed a sample dataset into the new tenant.
            'seed_demo_data' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
            'slug.not_in' => 'That slug is reserved and cannot be used.',
            'slug.unique' => 'A tenant with that slug already exists (it may be in the archive — restore or permanently delete it first).',
        ];
    }
}
