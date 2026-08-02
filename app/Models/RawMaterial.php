<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\HasSnapshot;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Raw material in the per-tenant catalog. Lives on the default connection, which
 * InitializeTenancyByPath has switched to the tenant database.
 *
 * @property int $id
 * @property string $name
 * @property string $sku
 * @property string|null $barcode
 * @property string $unit
 * @property int|null $created_by
 * @property-read User|null $creator
 * @property-read Collection<int, PurchaseOrderItem> $receivedPurchases
 */
#[Fillable(['name', 'sku', 'barcode', 'unit'])]
class RawMaterial extends Model
{
    use HasSnapshot;
    use RecordsActivity;
    use RecordsCreator;
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
     * Received purchase-order lines for this material — its "came from" history
     * (R10). Only POs that have actually been received count as a source.
     *
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function receivedPurchases(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)
            ->whereHas('purchaseOrder', fn (Builder $query): Builder => $query->where('status', PurchaseOrderStatus::Received));
    }
}
