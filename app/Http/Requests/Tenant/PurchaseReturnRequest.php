<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrderItem;
use App\Support\ActiveExists;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;

/** Create/update a purchase return with its line items. */
class PurchaseReturnRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // A return goes back to a specific supplier, so the item check below has
            // something to validate against.
            'supplier_id' => ['required', ActiveExists::of('suppliers')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.raw_material_id' => ['required', ActiveExists::of('raw_materials')],
            'items.*.quantity' => ['required', ...$this->decimalRules()],
        ];
    }

    /**
     * You can only return what you actually received from that supplier: each item's raw
     * material must appear on a RECEIVED purchase order for the selected supplier.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $supplierId = $this->integer('supplier_id');
            if ($supplierId === 0) {
                return; // missing/invalid supplier is already reported by the rules above
            }

            $receivedIds = PurchaseOrderItem::query()
                ->whereHas('purchaseOrder', function (Builder $query) use ($supplierId): void {
                    $query->where('supplier_id', $supplierId)
                        ->where('status', PurchaseOrderStatus::Received);
                })
                ->pluck('raw_material_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach ($this->array('items') as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $rawMaterialId = (int) ($item['raw_material_id'] ?? 0);
                if ($rawMaterialId !== 0 && ! in_array($rawMaterialId, $receivedIds, true)) {
                    $validator->errors()->add(
                        "items.{$index}.raw_material_id",
                        'This item was not received from the selected supplier.',
                    );
                }
            }
        });
    }
}
