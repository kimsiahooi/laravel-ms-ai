<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\ActiveExists;

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
                ActiveExists::of('warehouses'),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
