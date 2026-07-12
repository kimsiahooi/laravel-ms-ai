<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\ActiveExists;

class BomRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // A product may legitimately have an empty BOM, so `items` is present but can be [].
            'items' => ['present', 'array'],
            'items.*.raw_material_id' => [
                'required',
                'distinct',
                ActiveExists::of('raw_materials'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
