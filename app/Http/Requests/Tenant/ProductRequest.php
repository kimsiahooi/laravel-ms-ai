<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is already gated by auth:web; belt-and-suspenders.
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (in_array($this->input('min_stock'), [null, ''], true)) {
            $this->merge(['min_stock' => 0]);
        }
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
