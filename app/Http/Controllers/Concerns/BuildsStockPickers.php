<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Data\OptionData;
use App\Models\Location;
use App\Models\Product;
use App\Models\RawMaterial;
use Spatie\LaravelData\DataCollection;

/**
 * Shared form-picker options for the inventory screens (stock movements + transfers):
 * the location list and the merged product/raw-material item list.
 */
trait BuildsStockPickers
{
    /**
     * Locations for a picker, each labelled "Warehouse · code".
     *
     * @return DataCollection<int, OptionData>
     */
    protected function stockLocationOptions(): DataCollection
    {
        return OptionData::collect(
            Location::with('warehouse')
                ->orderBy('code')
                ->get()
                ->map(fn (Location $location): array => [
                    'id' => $location->id,
                    'name' => ($location->warehouse?->name ?? '?').' · '.$location->code,
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
}
