<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\RawMaterial;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\StockTake;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

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
     * Every registered list-export key. `reports` is exported too but handled
     * separately (it isn't a single-table list), so it isn't in here.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::configs());
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
            'purchase-orders' => [
                'query' => fn (string $s) => PurchaseOrder::query()->with(['supplier', 'items'])->search($s)->latest()->latest('id'),
                'columns' => [
                    ['heading' => 'Order #', 'value' => fn (PurchaseOrder $o) => $o->id],
                    ['heading' => 'Supplier', 'value' => fn (PurchaseOrder $o) => $o->supplier?->name],
                    ['heading' => 'Status', 'value' => fn (PurchaseOrder $o) => $o->status->label()],
                    ['heading' => 'Currency', 'value' => fn (PurchaseOrder $o) => $o->currency],
                    ['heading' => 'Items', 'value' => fn (PurchaseOrder $o) => $o->items->count()],
                    ['heading' => 'Total', 'value' => fn (PurchaseOrder $o) => (float) $o->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_cost)],
                    ['heading' => 'Created', 'value' => fn (PurchaseOrder $o) => $o->created_at?->format('Y-m-d')],
                ],
            ],
            'sales-orders' => [
                'query' => fn (string $s) => SalesOrder::query()->with(['customer', 'items'])->search($s)->latest()->latest('id'),
                'columns' => [
                    ['heading' => 'Order #', 'value' => fn (SalesOrder $o) => $o->id],
                    ['heading' => 'Customer', 'value' => fn (SalesOrder $o) => $o->customer?->name],
                    ['heading' => 'Status', 'value' => fn (SalesOrder $o) => $o->status->label()],
                    ['heading' => 'Currency', 'value' => fn (SalesOrder $o) => $o->currency],
                    ['heading' => 'Items', 'value' => fn (SalesOrder $o) => $o->items->count()],
                    ['heading' => 'Total', 'value' => fn (SalesOrder $o) => (float) $o->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_price)],
                    ['heading' => 'Created', 'value' => fn (SalesOrder $o) => $o->created_at?->format('Y-m-d')],
                ],
            ],
            'production-orders' => [
                'query' => fn (string $s) => ProductionOrder::query()->with('product')->search($s)->latest()->latest('id'),
                'columns' => [
                    ['heading' => 'Order #', 'value' => fn (ProductionOrder $o) => $o->id],
                    ['heading' => 'Product', 'value' => fn (ProductionOrder $o) => $o->product_snapshot['name'] ?? $o->product?->name],
                    ['heading' => 'Quantity', 'value' => fn (ProductionOrder $o) => (float) $o->quantity],
                    ['heading' => 'Status', 'value' => fn (ProductionOrder $o) => $o->status->label()],
                    ['heading' => 'Created', 'value' => fn (ProductionOrder $o) => $o->created_at?->format('Y-m-d')],
                ],
            ],
            'purchase-returns' => [
                'query' => fn (string $s) => PurchaseReturn::query()->with(['supplier', 'items'])->search($s)->latest()->latest('id'),
                'columns' => [
                    ['heading' => 'Return #', 'value' => fn (PurchaseReturn $r) => $r->id],
                    ['heading' => 'Supplier', 'value' => fn (PurchaseReturn $r) => $r->supplier?->name],
                    ['heading' => 'Status', 'value' => fn (PurchaseReturn $r) => $r->status->label()],
                    ['heading' => 'Items', 'value' => fn (PurchaseReturn $r) => $r->items->count()],
                    ['heading' => 'Created', 'value' => fn (PurchaseReturn $r) => $r->created_at?->format('Y-m-d')],
                ],
            ],
            'sales-returns' => [
                'query' => fn (string $s) => SalesReturn::query()->with(['customer', 'items'])->search($s)->latest()->latest('id'),
                'columns' => [
                    ['heading' => 'Return #', 'value' => fn (SalesReturn $r) => $r->id],
                    ['heading' => 'Customer', 'value' => fn (SalesReturn $r) => $r->customer?->name],
                    ['heading' => 'Status', 'value' => fn (SalesReturn $r) => $r->status->label()],
                    ['heading' => 'Items', 'value' => fn (SalesReturn $r) => $r->items->count()],
                    ['heading' => 'Created', 'value' => fn (SalesReturn $r) => $r->created_at?->format('Y-m-d')],
                ],
            ],
            'stock-takes' => [
                'query' => fn (string $s) => StockTake::query()->with('warehouse')->search($s)->latest()->latest('id'),
                'columns' => [
                    ['heading' => 'Count #', 'value' => fn (StockTake $t) => $t->id],
                    ['heading' => 'Warehouse', 'value' => fn (StockTake $t) => $t->warehouse?->name],
                    ['heading' => 'Status', 'value' => fn (StockTake $t) => $t->status->label()],
                    ['heading' => 'Counted at', 'value' => fn (StockTake $t) => $t->counted_at?->format('Y-m-d H:i')],
                    ['heading' => 'Created', 'value' => fn (StockTake $t) => $t->created_at?->format('Y-m-d')],
                ],
            ],
            'activity' => [
                'query' => fn (string $s) => Activity::query()->with(['causer', 'subject'])->latest('id'),
                'columns' => [
                    ['heading' => 'When', 'value' => fn (Activity $a) => $a->created_at?->format('Y-m-d H:i')],
                    ['heading' => 'Event', 'value' => fn (Activity $a) => $a->event],
                    ['heading' => 'Description', 'value' => fn (Activity $a) => $a->description],
                    ['heading' => 'Record', 'value' => fn (Activity $a) => $a->subject_type ? class_basename($a->subject_type) : null],
                    ['heading' => 'By', 'value' => fn (Activity $a) => $a->causer?->name],
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
