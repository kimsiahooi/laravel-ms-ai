<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\WarehouseReorderLevelRequest;
use App\Models\Warehouse;
use App\Models\WarehouseReorderLevel;
use Illuminate\Http\RedirectResponse;

class WarehouseReorderLevelController
{
    use RespondsWithToast;

    public function update(WarehouseReorderLevelRequest $request, Warehouse $warehouse): RedirectResponse
    {
        WarehouseReorderLevel::updateOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'stockable_type' => (string) $request->string('stockable_type'),
                'stockable_id' => $request->integer('stockable_id'),
            ],
            ['min_stock' => $request->float('min_stock')],
        );

        $this->toast('Reorder level updated.');

        return back();
    }
}
