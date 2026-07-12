<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Data\OptionData;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\DataCollection;

/**
 * Shared form-picker options for the inventory screens (stock movements + transfers):
 * the warehouse list and the merged product/raw-material item list.
 */
trait BuildsStockPickers
{
    /**
     * Warehouses for a picker, each labelled "Site · Warehouse".
     *
     * @return DataCollection<int, OptionData>
     */
    protected function stockWarehouseOptions(): DataCollection
    {
        return OptionData::collect(
            Warehouse::with('location')
                ->orderBy('name')
                ->get()
                ->map(fn (Warehouse $warehouse): array => [
                    'id' => $warehouse->id,
                    'name' => ($warehouse->location?->name ?? '?').' · '.$warehouse->name,
                ]),
            DataCollection::class,
        );
    }

    /**
     * One merged item picker: products + raw materials, valued "product:5" /
     * "raw_material:3" so the store action can resolve either type.
     *
     * @return array<int, array{value: string, label: string}>
     */
    protected function stockItemOptions(): array
    {
        return Product::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Product $product): array => [
                'value' => 'product:'.$product->id,
                'label' => $product->name.' · Product',
            ])
            ->concat(
                RawMaterial::orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (RawMaterial $rawMaterial): array => [
                        'value' => 'raw_material:'.$rawMaterial->id,
                        'label' => $rawMaterial->name.' · Raw material',
                    ]),
            )
            ->values()
            ->all();
    }

    /**
     * Resolve a merged-picker value ("product:5" / "raw_material:3") back to its
     * model — the inverse of stockItemOptions(). Assumes a well-formed value; the
     * FormRequests validate the shape and existence before this runs.
     */
    protected function resolveStockable(string $value): Model
    {
        [$type, $id] = explode(':', $value, 2);

        return $type === 'product'
            ? Product::findOrFail($id)
            : RawMaterial::findOrFail($id);
    }
}
