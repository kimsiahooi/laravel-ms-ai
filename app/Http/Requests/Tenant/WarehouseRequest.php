<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Warehouse;
use Illuminate\Validation\Rule;

class WarehouseRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $warehouse = $this->route('warehouse');
        $ignoreId = $warehouse instanceof Warehouse ? $warehouse->getKey() : null;

        return [
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:50',
                // Unique within this tenant's database (ignore self on update).
                Rule::unique('warehouses', 'code')->ignore($ignoreId),
            ],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
