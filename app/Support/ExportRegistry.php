<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Central config for the table exports: per resource, the (search-filtered) query
 * and the columns (heading + a value resolver). Adding a resource here plus an
 * `exportResource` prop on its DataTable is all it takes to give a list export.
 */
class ExportRegistry
{
    /**
     * @return array{query: callable(string): Builder<*>, columns: array<int, array{heading: string, value: callable}>}|null
     */
    public static function find(string $resource): ?array
    {
        return self::configs()[$resource] ?? null;
    }

    /**
     * @return array<string, array{query: callable, columns: array<int, array{heading: string, value: callable}>}>
     */
    private static function configs(): array
    {
        $morphLabel = fn (string $type): string => $type === 'product' ? 'Product' : 'Raw material';

        return [
            'categories' => [
                'query' => fn (string $s) => Category::query()->search($s)->orderBy('name'),
                'columns' => [
                    ['heading' => 'Name', 'value' => fn (Category $c) => $c->name],
                    ['heading' => 'Description', 'value' => fn (Category $c) => $c->description],
                ],
            ],
            'suppliers' => [
                'query' => fn (string $s) => Supplier::query()->search($s)->orderBy('name'),
                'columns' => self::partyColumns(),
            ],
            'customers' => [
                'query' => fn (string $s) => Customer::query()->search($s)->orderBy('name'),
                'columns' => self::partyColumns(),
            ],
            'raw-materials' => [
                'query' => fn (string $s) => RawMaterial::query()->search($s)->orderBy('name'),
                'columns' => [
                    ['heading' => 'Name', 'value' => fn (RawMaterial $m) => $m->name],
                    ['heading' => 'SKU', 'value' => fn (RawMaterial $m) => $m->sku],
                    ['heading' => 'Unit', 'value' => fn (RawMaterial $m) => $m->unit],
                ],
            ],
            'products' => [
                'query' => fn (string $s) => Product::query()->with(['category', 'supplier'])->search($s)->orderBy('name'),
                'columns' => [
                    ['heading' => 'Name', 'value' => fn (Product $p) => $p->name],
                    ['heading' => 'SKU', 'value' => fn (Product $p) => $p->sku],
                    ['heading' => 'Barcode', 'value' => fn (Product $p) => $p->barcode],
                    ['heading' => 'Unit', 'value' => fn (Product $p) => $p->unit],
                    ['heading' => 'Category', 'value' => fn (Product $p) => $p->category?->name],
                    ['heading' => 'Supplier', 'value' => fn (Product $p) => $p->supplier?->name],
                ],
            ],
            'locations' => [
                'query' => fn (string $s) => Location::query()->search($s)->orderBy('name'),
                'columns' => [
                    ['heading' => 'Name', 'value' => fn (Location $l) => $l->name],
                    ['heading' => 'Code', 'value' => fn (Location $l) => $l->code],
                    ['heading' => 'Address', 'value' => fn (Location $l) => $l->address],
                ],
            ],
            'warehouses' => [
                'query' => fn (string $s) => Warehouse::query()->with('location')->search($s)->orderBy('name'),
                'columns' => [
                    ['heading' => 'Name', 'value' => fn (Warehouse $w) => $w->name],
                    ['heading' => 'Code', 'value' => fn (Warehouse $w) => $w->code],
                    ['heading' => 'Location', 'value' => fn (Warehouse $w) => $w->location?->name],
                    ['heading' => 'Address', 'value' => fn (Warehouse $w) => $w->address],
                ],
            ],
            'stock-movements' => [
                'query' => fn (string $s) => StockMovement::query()->with(['warehouse.location', 'stockable', 'user'])->latest(),
                'columns' => [
                    ['heading' => 'When', 'value' => fn (StockMovement $m) => $m->created_at?->format('Y-m-d H:i')],
                    ['heading' => 'Warehouse', 'value' => fn (StockMovement $m) => $m->warehouse?->name],
                    ['heading' => 'Item', 'value' => fn (StockMovement $m) => $m->stockable?->name],
                    ['heading' => 'Type', 'value' => fn (StockMovement $m) => $morphLabel($m->stockable_type)],
                    ['heading' => 'Quantity', 'value' => fn (StockMovement $m) => (float) $m->quantity],
                    ['heading' => 'Reason', 'value' => fn (StockMovement $m) => $m->reason->label()],
                    ['heading' => 'By', 'value' => fn (StockMovement $m) => $m->user?->name],
                ],
            ],
            'stock-transfers' => [
                'query' => fn (string $s) => StockTransfer::query()->with(['fromWarehouse', 'toWarehouse', 'stockable', 'user'])->latest(),
                'columns' => [
                    ['heading' => 'When', 'value' => fn (StockTransfer $t) => $t->created_at?->format('Y-m-d H:i')],
                    ['heading' => 'Item', 'value' => fn (StockTransfer $t) => $t->stockable?->name],
                    ['heading' => 'From', 'value' => fn (StockTransfer $t) => $t->fromWarehouse?->name],
                    ['heading' => 'To', 'value' => fn (StockTransfer $t) => $t->toWarehouse?->name],
                    ['heading' => 'Quantity', 'value' => fn (StockTransfer $t) => (float) $t->quantity],
                    ['heading' => 'By', 'value' => fn (StockTransfer $t) => $t->user?->name],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{heading: string, value: callable}>
     */
    private static function partyColumns(): array
    {
        return [
            ['heading' => 'Name', 'value' => fn ($p) => $p->name],
            ['heading' => 'Email', 'value' => fn ($p) => $p->email],
            ['heading' => 'Phone', 'value' => fn ($p) => $p->phone],
            ['heading' => 'Address', 'value' => fn ($p) => $p->address],
        ];
    }
}
