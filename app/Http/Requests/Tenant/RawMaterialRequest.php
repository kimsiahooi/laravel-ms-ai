<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\RawMaterial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RawMaterialRequest extends FormRequest
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
        $rawMaterial = $this->route('rawMaterial');
        $ignoreId = $rawMaterial instanceof RawMaterial ? $rawMaterial->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required', 'string', 'max:100',
                Rule::unique('raw_materials', 'sku')->ignore($ignoreId),
            ],
            'unit' => ['required', 'string', 'max:20'],
            'min_stock' => ['required', 'numeric', 'min:0'],
        ];
    }
}
