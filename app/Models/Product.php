<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Product in the per-tenant catalog. Lives on the default connection, which
 * InitializeTenancyByPath has switched to the tenant database.
 *
 * @property int $id
 * @property string $name
 * @property string $sku
 * @property string|null $barcode
 * @property string|null $description
 * @property int|null $category_id
 * @property int|null $supplier_id
 * @property int $min_stock
 * @property string $unit
 * @property string|null $image
 * @property-read string|null $image_url
 * @property-read Collection<int, BomItem> $bomItems
 */
#[Fillable([
    'name', 'sku', 'barcode', 'description',
    'category_id', 'supplier_id', 'min_stock', 'unit', 'image',
])]
class Product extends Model
{
    use Searchable;
    use SoftDeletes;

    protected array $searchable = ['name', 'sku', 'barcode'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_stock' => 'integer'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The product's bill of materials (raw materials + per-unit quantity).
     *
     * @return HasMany<BomItem, $this>
     */
    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    /**
     * Public URL for the stored image, served through the per-product image route
     * (extension-less so nginx routes it to Laravel; tenant_asset() can't be used —
     * its route is domain-identified, but this app is path/slug-identified). Null
     * when no image is set. Only valid inside a tenant context (all product routes are).
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->image === null
                ? null
                : route('tenant.products.image', [
                    'tenant' => tenant('id'),
                    'product' => $this->id,
                ]),
        );
    }
}
