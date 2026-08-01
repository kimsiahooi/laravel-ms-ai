<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrderItem;
use App\Support\ActiveExists;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;

/** Create/update a sales return with its line items. */
class SalesReturnRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // A return comes back from a specific customer, so the item check below
            // has something to validate against.
            'customer_id' => ['required', ActiveExists::of('customers')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', ActiveExists::of('products')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:'.self::DECIMAL_MAX],
        ];
    }

    /**
     * You can only return what was actually sold to that customer: each item's product
     * must appear on a FULFILLED sales order for the selected customer.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $customerId = $this->integer('customer_id');
            if ($customerId === 0) {
                return; // missing/invalid customer is already reported by the rules above
            }

            $soldIds = SalesOrderItem::query()
                ->whereHas('salesOrder', function (Builder $query) use ($customerId): void {
                    $query->where('customer_id', $customerId)
                        ->where('status', SalesOrderStatus::Fulfilled);
                })
                ->pluck('product_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach ($this->array('items') as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId !== 0 && ! in_array($productId, $soldIds, true)) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        'This product was not sold to the selected customer.',
                    );
                }
            }
        });
    }
}
