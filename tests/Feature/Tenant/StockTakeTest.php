<?php

use App\Actions\ProvisionTenant;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTake;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\StockService;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/** Seed a warehouse holding 10 of one product. @return array{warehouse: int, product: int} */
function seedStockTakeFixture(): array
{
    return test()->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);
        $warehouse = Warehouse::create(['location_id' => $location->id, 'name' => 'Main']);
        $product = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);

        app(StockService::class)->record(
            Warehouse::find($warehouse->id),
            Product::find($product->id),
            10,
            StockMovementReason::Adjustment,
        );

        return ['warehouse' => $warehouse->id, 'product' => $product->id];
    });
}

it('redirects a guest from the stock takes page to the tenant login', function () {
    $this->get('/acme/stock-takes')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('starts a stock take that snapshots the warehouse on-hand', function () {
    ['warehouse' => $wh, 'product' => $p] = seedStockTakeFixture();
    loginAsAcmeUser();

    $this->post('/acme/stock-takes', ['warehouse_id' => $wh])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->tenant->run(function () use ($p) {
        $take = StockTake::with('items')->first();
        expect($take)->not->toBeNull()
            ->and($take->status->value)->toBe('draft')
            ->and($take->items)->toHaveCount(1)
            ->and((float) $take->items->first()->system_qty)->toBe(10.0)
            ->and((float) $take->items->first()->counted_qty)->toBe(10.0)
            ->and($take->items->first()->stockable_id)->toBe($p);
    });
});

it('posts variance adjustments and sets on-hand to the counted quantity', function () {
    ['warehouse' => $wh, 'product' => $p] = seedStockTakeFixture();
    loginAsAcmeUser();

    $this->post('/acme/stock-takes', ['warehouse_id' => $wh]);
    [$takeId, $itemId] = $this->tenant->run(function () {
        $take = StockTake::with('items')->first();

        return [$take->id, $take->items->first()->id];
    });

    $this->post("/acme/stock-takes/{$takeId}/post", [
        'items' => [['id' => $itemId, 'counted_qty' => 7]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->tenant->run(function () use ($wh, $p, $takeId) {
        $take = StockTake::with('items')->find($takeId);
        expect($take->status->value)->toBe('posted')
            ->and((float) $take->items->first()->counted_qty)->toBe(7.0)
            ->and((float) $take->items->first()->variance)->toBe(-3.0);

        $stock = WarehouseStock::where('warehouse_id', $wh)
            ->where('stockable_type', 'product')
            ->where('stockable_id', $p)
            ->first();
        expect((float) $stock->quantity)->toBe(7.0);

        $movement = StockMovement::where('reason', 'stock_take')->first();
        expect($movement)->not->toBeNull()
            ->and((float) $movement->quantity)->toBe(-3.0);
    });
});

it('refuses to post a stock take twice', function () {
    ['warehouse' => $wh] = seedStockTakeFixture();
    loginAsAcmeUser();

    $this->post('/acme/stock-takes', ['warehouse_id' => $wh]);
    $takeId = $this->tenant->run(fn () => StockTake::first()->id);

    $this->post("/acme/stock-takes/{$takeId}/post", ['items' => []])->assertRedirect();
    $this->post("/acme/stock-takes/{$takeId}/post", ['items' => []])->assertStatus(422);
});
