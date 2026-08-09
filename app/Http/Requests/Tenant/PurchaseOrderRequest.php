<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Settings\BusinessSettings;
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
            'currency' => ['required', 'string', Rule::in(BusinessSettings::currencies())],
            // Base-currency units per 1 unit of the order currency. Optional: the
            // controller forces it to 1 for a base-currency order and defaults a
            // missing rate to 1, so an order can never be created without one.
            // Capped at the exchange_rate column's real ceiling — decimal(15,6) holds
            // 9 integer digits — so an over-large rate 422s instead of overflowing (500).
            // KNOWN GAP (out of scope here, behaviour not schema): still nullable, so
            // a direct POST that omits it on a foreign-currency order is stored at
            // rate 1.0. The browser always sends it. Precision is what's enforced.
            'exchange_rate' => ['nullable', 'numeric', 'decimal:0,6', 'gt:0', 'max:999999999'],
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
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.raw_material_id' => [
                'required',
                ActiveExists::of('raw_materials'),
            ],
            'items.*.quantity' => ['required', ...$this->decimalRules()],
            'items.*.unit_cost' => ['required', ...$this->decimalRules('min:0')],
        ];
    }
}
