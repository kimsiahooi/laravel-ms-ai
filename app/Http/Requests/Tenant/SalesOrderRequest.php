<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\ActiveExists;
use Illuminate\Validation\Rule;

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
            // Optional user-entered document number; unique among live orders.
            'number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('sales_orders', 'number')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('salesOrder')),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'expected_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                ActiveExists::of('products'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:'.self::DECIMAL_MAX],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:'.self::DECIMAL_MAX],
        ];
    }
}
