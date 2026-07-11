<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Location;
use Illuminate\Validation\Rule;

class LocationRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $location = $this->route('location');
        $ignoreId = $location instanceof Location ? $location->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:50',
                // Unique within this tenant's database (ignore self on update).
                Rule::unique('locations', 'code')->ignore($ignoreId),
            ],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
