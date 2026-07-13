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
use Inertia\Testing\AssertableInertia as Assert;

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

/** Create a draft stock take at $warehouseId (no items needed for the list). */
function makeDraftStockTake(int $warehouseId): int
{
    return test()->tenant->run(fn () => StockTake::create([
        'warehouse_id' => $warehouseId,
        'status' => 'draft',
    ])->id);
}

it('filters, paginates and orders the stock takes index by query params', function () {
    seedStockTakeFixture();

    [$alpha, $beta] = $this->tenant->run(function () {
        $location = Location::create(['name' => 'Depots']);

        return [
            Warehouse::create(['location_id' => $location->id, 'name' => 'Alpha Depot'])->id,
            Warehouse::create(['location_id' => $location->id, 'name' => 'Beta Depot'])->id,
        ];
    });
    $id1 = makeDraftStockTake($alpha);
    $id2 = makeDraftStockTake($beta);
    $id3 = makeDraftStockTake($alpha);

    loginAsAcmeUser();

    // ?search matches the warehouse name.
    $this->get('/acme/stock-takes?search=Beta')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('takes.data', 1)
            ->where('takes.data.0.id', $id2)
            ->where('filters.search', 'Beta'));

    $this->get('/acme/stock-takes?search=Zenith')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('takes.data', 0)
            ->where('filters.search', 'Zenith'));

    $this->get('/acme/stock-takes?per_page=25')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('takes.data', 3)
            ->where('takes.per_page', 25));

    $this->get('/acme/stock-takes')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('takes.data.0.id', $id3)
            ->where('takes.data.2.id', $id1));
});

it('cancels a draft stock take but not again', function () {
    ['warehouse' => $wh] = seedStockTakeFixture();
    $takeId = makeDraftStockTake($wh);

    loginAsAcmeUser();

    $this->from('/acme/stock-takes')
        ->post("/acme/stock-takes/{$takeId}/cancel")
        ->assertRedirect('/acme/stock-takes')
        ->assertToast('Stock take cancelled.');

    $this->tenant->run(fn () => expect(StockTake::find($takeId)->status->value)->toBe('cancelled'));

    $this->post("/acme/stock-takes/{$takeId}/cancel")->assertStatus(422);
});

it('deletes a stock take', function () {
    ['warehouse' => $wh] = seedStockTakeFixture();
    $takeId = makeDraftStockTake($wh);

    loginAsAcmeUser();

    $this->from('/acme/stock-takes')
        ->delete("/acme/stock-takes/{$takeId}")
        ->assertRedirect('/acme/stock-takes')
        ->assertToast('Stock take deleted.');

    $this->tenant->run(fn () => expect(StockTake::find($takeId))->toBeNull());
});
