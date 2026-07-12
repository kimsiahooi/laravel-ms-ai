<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\SalesReturnItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One sales-return line for the table + edit form. */
#[TypeScript]
class SalesReturnItemData extends Data
{
    public function __construct(
        public int $id,
        public ?int $product_id,
        /** Snapshot name (survives product edits/deletes). */
        public string $name,
        public float $quantity,
    ) {}

    public static function fromSalesReturnItem(SalesReturnItem $item): self
    {
        return new self(
            id: $item->id,
            product_id: $item->product_id,
            name: $item->product_snapshot['name'] ?? '—',
            quantity: (float) $item->quantity,
        );
    }
}
