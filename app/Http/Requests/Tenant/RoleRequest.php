<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\TenantPermissions;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/** Create/update a role and the permissions it grants. */
class RoleRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $role = $this->route('role');
        $ignoreId = $role instanceof Role ? $role->getKey() : null;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->ignore($ignoreId),
            ],
            'permissions' => ['array'],
            // Only permissions from the fixed catalog can be granted.
            'permissions.*' => [Rule::in(TenantPermissions::names())],
        ];
    }
}
