<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\BomItem;
use App\Support\ActiveExists;
use Illuminate\Contracts\Validation\Validator;

class ProductionOrderRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The line items are the product's exploded BOM, snapshotted server-side
            // at creation — the client only picks the product and how many to make.
            'product_id' => [
                'required',
                ActiveExists::of('products'),
            ],
            'quantity' => ['required', ...$this->decimalRules()],
            'notes' => ['nullable', 'string', 'max:1000'],
            'expected_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Each material requirement is `BOM quantity × order quantity`, and that product
     * is stored in its own decimal(15,4) column. Both factors can be individually
     * valid and still multiply out to something the column can't hold, which used to
     * surface as a 500 midway through creating the order.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Only skip when the two values this needs are themselves invalid —
            // an unrelated bad field shouldn't hide an impossible quantity.
            if ($validator->errors()->hasAny(['product_id', 'quantity'])) {
                return;
            }

            $largestPerUnit = (float) BomItem::query()
                ->where('product_id', $this->integer('product_id'))
                ->max('quantity');

            if ($largestPerUnit * $this->float('quantity') > self::DECIMAL_MAX) {
                $validator->errors()->add(
                    'quantity',
                    'This quantity needs more material than can be recorded. Build it in smaller batches.',
                );
            }
        });
    }
}
