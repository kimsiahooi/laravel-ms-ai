<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\PurchaseOrderItem;
use App\Models\RawMaterial;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The raw-material list-item payload. snake_case property names keep the
 * serialized JSON (and the generated TS) byte-identical to the previous
 * hand-mapped array. #[TypeScript] makes the transformer emit
 * App.Data.RawMaterialData.
 */
#[TypeScript]
class RawMaterialData extends Data
{
    /**
     * @param  array<int, RawMaterialPurchaseData>  $purchase_history
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $sku,
        public ?string $barcode,
        public string $unit,
        public string $created_at,
        public ?string $creator,
        #[DataCollectionOf(RawMaterialPurchaseData::class)]
        public array $purchase_history,
    ) {}

    public static function fromRawMaterial(RawMaterial $rawMaterial): self
    {
        return new self(
            id: $rawMaterial->id,
            name: $rawMaterial->name,
            sku: $rawMaterial->sku,
            barcode: $rawMaterial->barcode,
            unit: $rawMaterial->unit,
            created_at: $rawMaterial->created_at->toISOString(),
            creator: $rawMaterial->creator?->name,
            purchase_history: $rawMaterial->receivedPurchases
                ->sortByDesc(fn (PurchaseOrderItem $item): ?string => $item->purchaseOrder->received_at?->toISOString())
                ->map(fn (PurchaseOrderItem $item): RawMaterialPurchaseData => RawMaterialPurchaseData::from($item))
                ->values()
                ->all(),
        );
    }
}
