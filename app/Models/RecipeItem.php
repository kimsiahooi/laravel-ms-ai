<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a product's recipe: `quantity` of a raw material needed
 * to make one unit of the product. Lives on the default (tenant) connection.
 *
 * @property int $id
 * @property int $product_id
 * @property int $raw_material_id
 * @property string $quantity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Product $product
 * @property-read RawMaterial $rawMaterial
 */
#[Fillable(['product_id', 'raw_material_id', 'quantity'])]
class RecipeItem extends Model
{
    protected $table = 'recipe_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<RawMaterial, $this>
     */
    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
