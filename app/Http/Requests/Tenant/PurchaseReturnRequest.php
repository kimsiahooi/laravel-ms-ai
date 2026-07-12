<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Validation\Rule;

/** Create/update a purchase return with its line items. */
class PurchaseReturnRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.raw_material_id' => ['required', Rule::exists('raw_materials', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
