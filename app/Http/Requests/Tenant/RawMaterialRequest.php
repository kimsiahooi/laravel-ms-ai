<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\RawMaterial;
use Illuminate\Validation\Rule;

class RawMaterialRequest extends TenantFormRequest
{
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
        ];
    }
}
