<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Validation\Rule;

class RecipeRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // A product may legitimately have an empty recipe, so `items` is present but can be [].
            'items' => ['present', 'array'],
            'items.*.raw_material_id' => [
                'required',
                'distinct',
                Rule::exists('raw_materials', 'id')->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
