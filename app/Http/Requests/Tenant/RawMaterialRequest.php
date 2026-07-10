<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Concerns\NormalizesNumericInput;
use App\Models\RawMaterial;
use Illuminate\Validation\Rule;

class RawMaterialRequest extends TenantFormRequest
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
