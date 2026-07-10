<?php

use App\Actions\ProvisionTenant;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\LocationStock;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/**
 * Warehouse + two locations + a product, inside the tenant DB.
 *
 * @return array{from: int, to: int, product: int}
 */
function seedTransferFixture(): array
{
    return test()->tenant->run(function () {
        $warehouse = Warehouse::create(['name' => 'Main']);
        $from = Location::create(['warehouse_id' => $warehouse->id, 'code' => 'A-01']);
        $to = Location::create(['warehouse_id' => $warehouse->id, 'code' => 'B-01']);
        $product = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        return ['from' => $from->id, 'to' => $to->id, 'product' => $product->id];
    });
}

/** Put $qty of $productId on-hand at $locationId via the service. */
function seedSourceStock(int $locationId, int $productId, float $qty): void
{
    test()->tenant->run(function () use ($locationId, $productId, $qty) {
        app(StockService::class)->record(
            Location::find($locationId),
            Product::find($productId),
            $qty,
            StockMovementReason::Adjustment,
        );
    });
}

it('redirects a guest from the stock transfers page to the tenant login', function () {
    $this->get('/acme/stock-transfers')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('transfers stock between locations, moving on-hand and writing the ledger', function () {
    ['from' => $from, 'to' => $to, 'product' => $product] = seedTransferFixture();
    seedSourceStock($from, $product, 10);

    loginAsAcmeUser();

    $this->from('/acme/stock-transfers')
        ->post('/acme/stock-transfers', [
            'from_location_id' => $from,
            'to_location_id' => $to,
            'stockable' => "product:{$product}",
            'quantity' => 4,
        ])
        ->assertRedirect('/acme/stock-transfers')
        ->assertSessionHasNoErrors()
        ->assertToast('Transfer recorded.');

    $this->tenant->run(function () use ($from, $to, $product) {
        $sourceStock = LocationStock::where('location_id', $from)->where('stockable_id', $product)->first();
        $destStock = LocationStock::where('location_id', $to)->where('stockable_id', $product)->first();

        expect((float) $sourceStock->quantity)->toBe(6.0)
            ->and((float) $destStock->quantity)->toBe(4.0)
            ->and(StockTransfer::count())->toBe(1)
            // Two ledger rows for the transfer (out + in), plus the initial seed.
            ->and(StockMovement::where('reason', 'transfer_out')->where('location_id', $from)->count())->toBe(1)
            ->and(StockMovement::where('reason', 'transfer_in')->where('location_id', $to)->count())->toBe(1);
    });
});

it('rolls the whole transfer back when the source is short', function () {
    ['from' => $from, 'to' => $to, 'product' => $product] = seedTransferFixture();
    seedSourceStock($from, $product, 3);

    loginAsAcmeUser();

    $this->from('/acme/stock-transfers')
        ->post('/acme/stock-transfers', [
            'from_location_id' => $from,
            'to_location_id' => $to,
            'stockable' => "product:{$product}",
            'quantity' => 10,
        ])
        ->assertRedirect('/acme/stock-transfers')
        ->assertInvalid('quantity');

    $this->tenant->run(function () use ($from, $to, $product) {
        $sourceStock = LocationStock::where('location_id', $from)->where('stockable_id', $product)->first();

        // Nothing moved: source unchanged, no destination row, no transfer, no transfer ledger rows.
        expect((float) $sourceStock->quantity)->toBe(3.0)
            ->and(LocationStock::where('location_id', $to)->exists())->toBeFalse()
            ->and(StockTransfer::count())->toBe(0)
            ->and(StockMovement::whereIn('reason', ['transfer_in', 'transfer_out'])->count())->toBe(0);
    });
});

it('rejects a transfer to the same location', function () {
    ['from' => $from, 'product' => $product] = seedTransferFixture();
    seedSourceStock($from, $product, 10);

    loginAsAcmeUser();

    $this->from('/acme/stock-transfers')
        ->post('/acme/stock-transfers', [
            'from_location_id' => $from,
            'to_location_id' => $from,
            'stockable' => "product:{$product}",
            'quantity' => 4,
        ])
        ->assertRedirect('/acme/stock-transfers')
        ->assertInvalid('to_location_id');
});

it('rejects a non-positive quantity', function () {
    ['from' => $from, 'to' => $to, 'product' => $product] = seedTransferFixture();

    loginAsAcmeUser();

    $this->from('/acme/stock-transfers')
        ->post('/acme/stock-transfers', [
            'from_location_id' => $from,
            'to_location_id' => $to,
            'stockable' => "product:{$product}",
            'quantity' => 0,
        ])
        ->assertRedirect('/acme/stock-transfers')
        ->assertInvalid('quantity');
});

it('transfers a raw material', function () {
    ['from' => $from, 'to' => $to] = seedTransferFixture();

    $rawMaterialId = $this->tenant->run(
        fn () => RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg', 'min_stock' => 0])->id,
    );

    $this->tenant->run(function () use ($from, $rawMaterialId) {
        app(StockService::class)->record(
            Location::find($from),
            RawMaterial::find($rawMaterialId),
            50,
            StockMovementReason::Adjustment,
        );
    });

    loginAsAcmeUser();

    $this->from('/acme/stock-transfers')
        ->post('/acme/stock-transfers', [
            'from_location_id' => $from,
            'to_location_id' => $to,
            'stockable' => "raw_material:{$rawMaterialId}",
            'quantity' => 20,
        ])
        ->assertRedirect('/acme/stock-transfers')
        ->assertSessionHasNoErrors();

    $this->tenant->run(function () use ($to, $rawMaterialId) {
        $destStock = LocationStock::where('location_id', $to)
            ->where('stockable_type', 'raw_material')
            ->where('stockable_id', $rawMaterialId)
            ->first();

        expect((float) $destStock->quantity)->toBe(20.0)
            ->and(StockTransfer::where('stockable_type', 'raw_material')->exists())->toBeTrue();
    });
});

it('rejects a stockable that does not exist', function () {
    ['from' => $from, 'to' => $to] = seedTransferFixture();

    loginAsAcmeUser();

    $this->from('/acme/stock-transfers')
        ->post('/acme/stock-transfers', [
            'from_location_id' => $from,
            'to_location_id' => $to,
            'stockable' => 'product:999999',
            'quantity' => 4,
        ])
        ->assertRedirect('/acme/stock-transfers')
        ->assertInvalid('stockable');
});

it('lists the transfers with picker options', function () {
    ['from' => $from, 'to' => $to, 'product' => $product] = seedTransferFixture();
    seedSourceStock($from, $product, 10);

    loginAsAcmeUser();

    $this->post('/acme/stock-transfers', [
        'from_location_id' => $from,
        'to_location_id' => $to,
        'stockable' => "product:{$product}",
        'quantity' => 4,
    ]);

    $this->get('/acme/stock-transfers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/stock-transfers/index')
            ->has('transfers.data', 1)
            ->has('locations', 2)
            ->has('items', 1)
        );
});
