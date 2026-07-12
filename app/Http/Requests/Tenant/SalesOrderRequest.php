<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\ActiveExists;

class SalesOrderRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                ActiveExists::of('customers'),
            ],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                ActiveExists::of('products'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
