<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use App\Support\ActiveExists;
use Illuminate\Validation\Rule;

class ProductRequest extends TenantFormRequest
{
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
                ActiveExists::of('categories'),
            ],
            'supplier_id' => [
                'nullable',
                ActiveExists::of('suppliers'),
            ],
            'unit' => ['required', 'string', 'max:20'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}
