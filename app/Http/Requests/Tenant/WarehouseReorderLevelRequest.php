<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\ActiveExists;
use Illuminate\Validation\Rule;

class WarehouseReorderLevelRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Pick the exists() table from the raw, not-yet-validated stockable_type.
        // Safe only because the in: rule below always fails when this guess is
        // wrong (no `bail`, so all rule errors are still collected).
        $table = $this->input('stockable_type') === 'raw_material'
            ? 'raw_materials'
            : 'products';

        return [
            'stockable_type' => ['required', 'in:product,raw_material'],
            'stockable_id' => [
                'required', 'integer',
                ActiveExists::of($table),
            ],
            'min_stock' => ['required', 'numeric', 'min:0'],
        ];
    }
}
