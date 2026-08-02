<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Create/update a tenant user and their assigned role. */
class UserRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $ignoreId = $user instanceof User ? $user->getKey() : null;
        $isCreate = $ignoreId === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            // Admin sets the password on create; on edit it's optional (blank keeps
            // the current one). The model's `hashed` cast hashes it — never pre-hash.
            'password' => $isCreate ? ['required', Password::default()] : ['nullable', Password::default()],
        ];
    }
}
