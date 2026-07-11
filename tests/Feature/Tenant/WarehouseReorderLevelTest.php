<?php

use App\Actions\ProvisionTenant;
use App\Models\Location;
use App\Models\Product;
use App\Models\RawMaterial;
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

it('upserts a reorder level via the endpoint and flashes a toast', function () {
    [$wh, $widget] = $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        $wh = Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        return [$wh, $widget];
    });

    loginAsAcmeUser();
    $url = "/acme/warehouses/{$wh->id}/reorder-levels";

    $this->put($url, ['stockable_type' => 'product', 'stockable_id' => $widget->id, 'min_stock' => 30])
        ->assertRedirect();
    $this->put($url, ['stockable_type' => 'product', 'stockable_id' => $widget->id, 'min_stock' => 45])
        ->assertRedirect();

    $this->tenant->run(function () use ($wh, $widget) {
        $rows = WarehouseReorderLevel::where('warehouse_id', $wh->id)
            ->where('stockable_id', $widget->id)->where('stockable_type', 'product')->get();
        expect($rows)->toHaveCount(1)
            ->and((float) $rows->first()->min_stock)->toBe(45.0);
    });
});

it('accepts min_stock 0 and rejects invalid reorder-level input', function () {
    [$wh, $widget] = $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        $wh = Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        return [$wh, $widget];
    });

    loginAsAcmeUser();
    $url = "/acme/warehouses/{$wh->id}/reorder-levels";

    $this->put($url, ['stockable_type' => 'product', 'stockable_id' => $widget->id, 'min_stock' => 0])
        ->assertRedirect();
    $this->put($url, ['stockable_type' => 'product', 'stockable_id' => $widget->id, 'min_stock' => -1])
        ->assertSessionHasErrors('min_stock');
    $this->put($url, ['stockable_type' => 'widget', 'stockable_id' => $widget->id, 'min_stock' => 5])
        ->assertSessionHasErrors('stockable_type');
    $this->put($url, ['stockable_type' => 'product', 'stockable_id' => 999999, 'min_stock' => 5])
        ->assertSessionHasErrors('stockable_id');
});

it('requires auth for the reorder-level endpoint', function () {
    $wh = $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);

        return Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
    });

    $this->put("/acme/warehouses/{$wh->id}/reorder-levels", [
        'stockable_type' => 'product', 'stockable_id' => 1, 'min_stock' => 5,
    ])->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('upserts a reorder level for a raw material', function () {
    [$wh, $steel] = $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        $wh = Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        $steel = RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg']);

        return [$wh, $steel];
    });

    loginAsAcmeUser();

    $this->put("/acme/warehouses/{$wh->id}/reorder-levels", [
        'stockable_type' => 'raw_material', 'stockable_id' => $steel->id, 'min_stock' => 15,
    ])->assertRedirect();

    $this->tenant->run(function () use ($wh, $steel) {
        $row = WarehouseReorderLevel::where('warehouse_id', $wh->id)
            ->where('stockable_type', 'raw_material')->where('stockable_id', $steel->id)->first();
        expect($row)->not->toBeNull()->and((float) $row->min_stock)->toBe(15.0);
    });
});

it('rejects a stockable_id that exists in the other morph table only', function () {
    // A product id, submitted as stockable_type=raw_material, must fail the
    // exists() check against the raw_materials table (the whole point of the
    // per-type table selection).
    [$wh, $widget] = $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        $wh = Warehouse::create(['location_id' => $loc->id, 'name' => 'Main']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        return [$wh, $widget]; // no raw materials exist
    });

    loginAsAcmeUser();

    $this->put("/acme/warehouses/{$wh->id}/reorder-levels", [
        'stockable_type' => 'raw_material', 'stockable_id' => $widget->id, 'min_stock' => 5,
    ])->assertSessionHasErrors('stockable_id');
});
