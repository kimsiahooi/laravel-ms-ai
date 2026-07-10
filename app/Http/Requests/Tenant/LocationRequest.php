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
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->whereNull('deleted_at'),
            ],
            'code' => [
                'required', 'string', 'max:50',
                // Unique per warehouse within this tenant (ignore self on update).
                Rule::unique('locations', 'code')
                    ->where(fn ($query) => $query->where('warehouse_id', $this->input('warehouse_id')))
                    ->ignore($ignoreId),
            ],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
