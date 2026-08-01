<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\ActiveExists;
use Illuminate\Validation\Rule;

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
            // Optional user-entered document number; unique among live orders.
            'number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('purchase_orders', 'number')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('purchaseOrder')),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'expected_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.raw_material_id' => [
                'required',
                ActiveExists::of('raw_materials'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:'.self::DECIMAL_MAX],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:'.self::DECIMAL_MAX],
        ];
    }
}
