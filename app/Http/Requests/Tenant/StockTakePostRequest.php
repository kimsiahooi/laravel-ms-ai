<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\StockTake;
use Illuminate\Validation\Rule;

/**
 * The counted quantities submitted when a stock take is applied.
 *
 * These were validated inline in the controller, which is how they came to be the
 * only quantities in the app with no upper bound: a count larger than the
 * decimal(15,4) column could hold reached MySQL and returned a 500 part-way
 * through the transaction. Living here, they inherit the shared decimal rules.
 */
class StockTakePostRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $take = $this->route('stockTake');

        return [
            'items' => ['array', 'max:5000'],
            // Scoped to this take, so one take's lines can't be counted against another.
            'items.*.id' => [
                'required',
                'integer',
                Rule::exists('stock_take_items', 'id')
                    ->where('stock_take_id', $take instanceof StockTake ? $take->id : 0),
            ],
            // Counting nothing is a real answer, so zero is allowed here.
            'items.*.counted_qty' => ['required', ...$this->decimalRules('min:0')],
        ];
    }
}
