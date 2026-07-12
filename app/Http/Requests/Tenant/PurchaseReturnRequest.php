<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\ActiveExists;

/** Create/update a purchase return with its line items. */
class PurchaseReturnRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', ActiveExists::of('suppliers')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.raw_material_id' => ['required', ActiveExists::of('raw_materials')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
