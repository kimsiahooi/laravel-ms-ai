<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Validation\Rule;

/** Starts a stock take for a warehouse (the count lines are snapshotted server-side). */
class StockTakeRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->whereNull('deleted_at'),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
