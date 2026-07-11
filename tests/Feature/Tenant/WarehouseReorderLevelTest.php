<?php

use App\Actions\ProvisionTenant;
use App\Models\Location;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseReorderLevel;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('stores a per-warehouse reorder level and defaults min_stock to 0', function () {
    $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        $wh = Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        $level = WarehouseReorderLevel::create([
            'warehouse_id' => $wh->id,
            'stockable_type' => 'product',
            'stockable_id' => $widget->id,
            'min_stock' => 25,
        ]);

        expect((float) $level->min_stock)->toBe(25.0)
            ->and($level->warehouse->name)->toBe('Main')
            ->and($level->stockable->id)->toBe($widget->id);

        $blank = WarehouseReorderLevel::create([
            'warehouse_id' => $wh->id,
            'stockable_type' => 'product',
            'stockable_id' => Product::create(['name' => 'B', 'sku' => 'B-1', 'unit' => 'ea'])->id,
        ]);
        expect((float) $blank->min_stock)->toBe(0.0);
    });
});

it('enforces one reorder level per (warehouse, item)', function () {
    $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        $wh = Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        WarehouseReorderLevel::create([
            'warehouse_id' => $wh->id, 'stockable_type' => 'product',
            'stockable_id' => $widget->id, 'min_stock' => 5,
        ]);

        expect(fn () => WarehouseReorderLevel::create([
            'warehouse_id' => $wh->id, 'stockable_type' => 'product',
            'stockable_id' => $widget->id, 'min_stock' => 9,
        ]))->toThrow(QueryException::class);
    });
});
