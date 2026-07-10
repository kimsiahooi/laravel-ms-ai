<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Supplier;
use Illuminate\Validation\Rule;

class SupplierRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $ignoreId = $supplier instanceof Supplier ? $supplier->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'string', 'email', 'max:255',
                // Unique within this tenant's database (ignore self on update).
                Rule::unique('suppliers', 'email')->ignore($ignoreId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
