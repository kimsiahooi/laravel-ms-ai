<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Support\ActiveExists;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Validates the on-hand lookup query (?warehouse_id=&stockable=product:5). Mirrors
 * StockMovementRequest's stockable rules so the same picker value is accepted.
 */
class StockOnHandRequest extends TenantFormRequest
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
            'stockable' => ['required', 'string', 'regex:/^(product|raw_material):\d+$/'],
        ];
    }

    /**
     * The picker's internal key is "stockable"; call it "item" in user-facing errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['stockable' => 'item'];
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
