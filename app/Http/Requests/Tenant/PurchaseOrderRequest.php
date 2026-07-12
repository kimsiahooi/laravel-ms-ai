<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\ActiveExists;

class PurchaseOrderRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => [
                'required',
                ActiveExists::of('suppliers'),
            ],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.raw_material_id' => [
                'required',
                ActiveExists::of('raw_materials'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }
}
