<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSnapshot;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
 * @property string $unit
 * @property-read string|null $image_url
 * @property-read Collection<int, BomItem> $bomItems
 */
#[Fillable([
    'name', 'sku', 'barcode', 'description',
    'category_id', 'supplier_id', 'unit',
])]
class Product extends Model implements HasMedia
{
    use HasSnapshot;
    use InteractsWithMedia;
    use RecordsActivity;
    use Searchable;
    use SoftDeletes;

    protected array $searchable = ['name', 'sku', 'barcode'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
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
     * The product's BOM (raw materials + per-unit quantity).
     *
     * @return HasMany<BomItem, $this>
     */
    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    /**
     * A single product photo. `singleFile()` means a new upload replaces (and
     * deletes) the previous file automatically; deleting the media — or
     * force-deleting the product — removes the file. A soft delete keeps it.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    /**
     * Public URL for the stored image, served through the content-addressed media route
     * (`/{tenant}/media/{id}`, extension-less so nginx routes it to Laravel; tenant_asset()
     * can't be used — its route is domain-identified, but this app is path/slug-identified).
     * The URL carries the media id, so a re-upload (new id) yields a new URL — never stale.
     * Null when no image is set. Only valid inside a tenant context (all product routes are).
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $media = $this->getFirstMedia('image');

            return $media === null
                ? null
                : route('tenant.media', [
                    'tenant' => tenant('id'),
                    'media' => $media->getKey(),
                ]);
        });
    }
}
