<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Support\ActiveExists;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StockMovementRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                ActiveExists::of('warehouses'),
            ],
            // The merged item-picker value, e.g. "product:5" or "raw_material:3".
            'stockable' => ['required', 'string', 'regex:/^(product|raw_material):\d+$/'],
            'type' => ['required', Rule::in(['in', 'out', 'adjustment'])],
            // Magnitude for in/out; the absolute target for adjustment.
            'quantity' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Confirm the picked stockable row actually exists (the regex only checks shape).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $value = (string) $this->input('stockable');

            if (! preg_match('/^(product|raw_material):(\d+)$/', $value, $matches)) {
                return; // shape error already reported by the regex rule
            }

            $exists = $matches[1] === 'product'
                ? Product::whereKey($matches[2])->exists()
                : RawMaterial::whereKey($matches[2])->exists();

            if (! $exists) {
                $validator->errors()->add('stockable', 'The selected item does not exist.');
            }
        });
    }
}
