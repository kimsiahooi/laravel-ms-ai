<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Concerns\NormalizesNumericInput;
use App\Models\Product;
use Illuminate\Validation\Rule;

class ProductRequest extends TenantFormRequest
{
    use NormalizesNumericInput;

    protected function prepareForValidation(): void
    {
        $this->defaultBlankToZero('min_stock');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $ignoreId = $product instanceof Product ? $product->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required', 'string', 'max:100',
                Rule::unique('products', 'sku')->ignore($ignoreId),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
            ],
            'supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->whereNull('deleted_at'),
            ],
            'min_stock' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}
