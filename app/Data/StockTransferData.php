<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\StockTransfer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One flattened row for the Stock Transfers table. snake_case keys keep the JSON
 * byte-identical to the generated TS.
 */
#[TypeScript]
class StockTransferData extends Data
{
    public function __construct(
        public int $id,
        /** "Item name · Product|Raw material". */
        public string $item,
        /** Source "Site · Warehouse". */
        public string $from,
        /** Destination "Site · Warehouse". */
        public string $to,
        public float $quantity,
        public ?string $user,
        public string $created_at,
    ) {}

    public static function fromStockTransfer(StockTransfer $transfer): self
    {
        $kind = $transfer->stockable_type === 'product' ? 'Product' : 'Raw material';

        return new self(
            id: $transfer->id,
            item: ($transfer->stockable?->name ?? '—').' · '.$kind,
            from: ($transfer->fromWarehouse->location?->name ?? '?').' · '.$transfer->fromWarehouse->name,
            to: ($transfer->toWarehouse->location?->name ?? '?').' · '.$transfer->toWarehouse->name,
            quantity: (float) $transfer->quantity,
            user: $transfer->user?->name,
            created_at: $transfer->created_at->toISOString(),
        );
    }
}
