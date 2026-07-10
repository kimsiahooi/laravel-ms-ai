<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\SalesOrderItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One sales-order line for the table + edit form. */
#[TypeScript]
class SalesOrderItemData extends Data
{
    public function __construct(
        public int $id,
        public ?int $product_id,
        /** Snapshot name (survives product edits/deletes). */
        public string $name,
        public float $quantity,
        public float $unit_price,
    ) {}

    public static function fromSalesOrderItem(SalesOrderItem $item): self
    {
        return new self(
            id: $item->id,
            product_id: $item->product_id,
            name: $item->product_snapshot['name'] ?? '—',
            quantity: (float) $item->quantity,
            unit_price: (float) $item->unit_price,
        );
    }
}
