<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StockTransferRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from_location_id' => [
                'required',
                Rule::exists('locations', 'id')->whereNull('deleted_at'),
            ],
            'to_location_id' => [
                'required',
                'different:from_location_id',
                Rule::exists('locations', 'id')->whereNull('deleted_at'),
            ],
            'stockable' => ['required', 'string', 'regex:/^(product|raw_material):\d+$/'],
            'quantity' => ['required', 'numeric', 'gt:0'],
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
                return;
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
