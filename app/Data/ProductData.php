<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Product;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The product list-item payload. snake_case property names keep the serialized
 * JSON (and the generated TS) byte-identical to the previous hand-mapped array.
 * #[TypeScript] makes the transformer emit App.Data.ProductData.
 */
#[TypeScript]
class ProductData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $sku,
        public ?string $barcode,
        public ?string $description,
        public ?string $image_url,
        public ?int $category_id,
        public ?int $supplier_id,
        public ?string $category,
        public ?string $supplier,
        public int $min_stock,
        public string $unit,
        public string $created_at,
    ) {}

    public static function fromProduct(Product $product): self
    {
        return new self(
            id: $product->id,
            name: $product->name,
            sku: $product->sku,
            barcode: $product->barcode,
            description: $product->description,
            image_url: $product->image_url,
            category_id: $product->category_id,
            supplier_id: $product->supplier_id,
            category: $product->category?->name,
            supplier: $product->supplier?->name,
            min_stock: $product->min_stock,
            unit: $product->unit,
            created_at: $product->created_at->toISOString(),
        );
    }
}
