<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\ActiveExists;

/** Create/update a sales return with its line items. */
class SalesReturnRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', ActiveExists::of('customers')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', ActiveExists::of('products')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
