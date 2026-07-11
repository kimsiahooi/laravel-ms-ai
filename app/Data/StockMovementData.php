<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\StockMovement;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One flattened ledger row for the Stock Movements table. `quantity` is signed
 * (+ in / − out). snake_case keys keep the JSON byte-identical to the generated TS.
 */
#[TypeScript]
class StockMovementData extends Data
{
    public function __construct(
        public int $id,
        /** "Site · Warehouse", e.g. "KL HQ · Main Store". */
        public string $warehouse,
        /** "Item name · Product|Raw material". */
        public string $item,
        public float $quantity,
        public string $reason,
        public ?string $user,
        public string $created_at,
    ) {}

    public static function fromStockMovement(StockMovement $movement): self
    {
        $kind = $movement->stockable_type === 'product' ? 'Product' : 'Raw material';

        // `warehouse` is loaded withTrashed (append-only ledger), but stay defensive
        // in case the row can't be resolved at all.
        $warehouse = $movement->warehouse;

        return new self(
            id: $movement->id,
            warehouse: $warehouse
                ? ($warehouse->location?->name ?? '?').' · '.$warehouse->name
                : '—',
            item: ($movement->stockable?->name ?? '—').' · '.$kind,
            quantity: (float) $movement->quantity,
            reason: $movement->reason->label(),
            user: $movement->user?->name,
            created_at: $movement->created_at->toISOString(),
        );
    }
}
