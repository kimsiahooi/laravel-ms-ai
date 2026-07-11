<?php

use App\Actions\ProvisionTenant;
use App\Enums\StockMovementReason;
use App\Models\Location;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/**
 * A site + two warehouses (the stock holders) + a product, inside the tenant DB.
 *
 * @return array{from: int, to: int, product: int}
 */
function seedTransferFixture(): array
{
    return test()->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);
        $from = Warehouse::create(['location_id' => $location->id, 'name' => 'Main', 'code' => 'A-01']);
        $to = Warehouse::create(['location_id' => $location->id, 'name' => 'Overflow', 'code' => 'B-01']);
        $product = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);

        return ['from' => $from->id, 'to' => $to->id, 'product' => $product->id];
    });
}

/** Put $qty of $productId on-hand at $warehouseId via the service. */
function seedSourceStock(int $warehouseId, int $productId, float $qty): void
{
    test()->tenant->run(function () use ($warehouseId, $productId, $qty) {
        app(StockService::class)->record(
            Warehouse::find($warehouseId),
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

it('transfers stock between warehouses, moving on-hand and writing the ledger', function () {
    ['from' => $from, 'to' => $to, 'product' => $product] = seedTransferFixture();
    seedSourceStock($from, $product, 10);

    loginAsAcmeUser();

    $this->from('/acme/stock-transfers')
        ->post('/acme/stock-transfers', [
            'from_warehouse_id' => $from,
            'to_warehouse_id' => $to,
            'stockable' => "product:{$product}",
            'quantity' => 4,
        ])
        ->assertRedirect('/acme/stock-transfers')
        ->assertSessionHasNoErrors()
        ->assertToast('Transfer recorded.');

    $this->tenant->run(function () use ($from, $to, $product) {
        $sourceStock = WarehouseStock::where('warehouse_id', $from)->where('stockable_id', $product)->first();
        $destStock = WarehouseStock::where('warehouse_id', $to)->where('stockable_id', $product)->first();

        expect((float) $sourceStock->quantity)->toBe(6.0)
            ->and((float) $destStock->quantity)->toBe(4.0)
            ->and(StockTransfer::count())->toBe(1)
            // Two ledger rows for the transfer (out + in), plus the initial seed.
            ->and(StockMovement::where('reason', 'transfer_out')->where('warehouse_id', $from)->count())->toBe(1)
            ->and(StockMovement::where('reason', 'transfer_in')->where('warehouse_id', $to)->count())->toBe(1);
    });
});

it('rolls the whole transfer back when the source is short', function () {
    ['from' => $from, 'to' => $to, 'product' => $product] = seedTransferFixture();
    seedSourceStock($from, $product, 3);

    loginAsAcmeUser();

    $this->from('/acme/stock-transfers')
        ->post('/acme/stock-transfers', [
            'from_warehouse_id' => $from,
            'to_warehouse_id' => $to,
            'stockable' => "product:{$product}",
            'quantity' => 10,
        ])
        ->assertRedirect('/acme/stock-transfers')
        ->assertInvalid('quantity');

    $this->tenant->run(function () use ($from, $to, $product) {
        $sourceStock = WarehouseStock::where('warehouse_id', $from)->where('stockable_id', $product)->first();

        // Nothing moved: source unchanged, no destination row, no transfer, no transfer ledger rows.
        expect((float) $sourceStock->quantity)->toBe(3.0)
            ->and(WarehouseStock::where('warehouse_id', $to)->exists())->toBeFalse()
            ->and(StockTransfer::count())->toBe(0)
            ->and(StockMovement::whereIn('reason', ['transfer_in', 'transfer_out'])->count())->toBe(0);
    });
});

it('rejects a transfer to the same warehouse', function () {
    ['from' => $from, 'product' => $product] = seedTransferFixture();
    seedSourceStock($from, $product, 10);

    loginAsAcmeUser();

    $this->from('/acme/stock-transfers')
        ->post('/acme/stock-transfers', [
            'from_warehouse_id' => $from,
            'to_warehouse_id' => $from,
            'stockable' => "product:{$product}",
            'quantity' => 4,
        ])
        ->assertRedirect('/acme/stock-transfers')
        ->assertInvalid('to_warehouse_id');
});

it('rejects a non-positive quantity', function () {
    ['from' => $from, 'to' => $to, 'product' => $product] = seedTransferFixture();

    loginAsAcmeUser();

    $this->from('/acme/stock-transfers')
        ->post('/acme/stock-transfers', [
            'from_warehouse_id' => $from,
            'to_warehouse_id' => $to,
            'stockable' => "product:{$product}",
            'quantity' => 0,
        ])
        ->assertRedirect('/acme/stock-transfers')
        ->assertInvalid('quantity');
});

it('transfers a raw material', function () {
    ['from' => $from, 'to' => $to] = seedTransferFixture();

    $rawMaterialId = $this->tenant->run(
        fn () => RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg'])->id,
    );

    $this->tenant->run(function () use ($from, $rawMaterialId) {
        app(StockService::class)->record(
            Warehouse::find($from),
            RawMaterial::find($rawMaterialId),
            50,
            StockMovementReason::Adjustment,
        );
    });

    loginAsAcmeUser();

    $this->from('/acme/stock-transfers')
        ->post('/acme/stock-transfers', [
            'from_warehouse_id' => $from,
            'to_warehouse_id' => $to,
            'stockable' => "raw_material:{$rawMaterialId}",
            'quantity' => 20,
        ])
        ->assertRedirect('/acme/stock-transfers')
        ->assertSessionHasNoErrors();

    $this->tenant->run(function () use ($to, $rawMaterialId) {
        $destStock = WarehouseStock::where('warehouse_id', $to)
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
            'from_warehouse_id' => $from,
            'to_warehouse_id' => $to,
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
        'from_warehouse_id' => $from,
        'to_warehouse_id' => $to,
        'stockable' => "product:{$product}",
        'quantity' => 4,
    ]);

    $this->get('/acme/stock-transfers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/stock-transfers/index')
            ->has('transfers.data', 1)
            ->has('warehouses', 2)
            ->has('items', 1)
        );
});

it('filters the transfer ledger by item name and by endpoint warehouse', function () {
    test()->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);
        $from = Warehouse::create(['location_id' => $location->id, 'name' => 'Main', 'code' => 'A-01']);
        $to = Warehouse::create(['location_id' => $location->id, 'name' => 'Overflow', 'code' => 'B-01']);
        $widget = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'ea']);
        $gadget = Product::create(['name' => 'Gadget', 'sku' => 'G-1', 'unit' => 'ea']);

        foreach ([$widget->id, $gadget->id] as $id) {
            StockTransfer::create([
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'stockable_type' => 'product',
                'stockable_id' => $id,
                'quantity' => 3,
            ]);
        }
    });

    loginAsAcmeUser();

    // Item-name match runs across the polymorphic stockable.
    $this->get('/acme/stock-transfers?search=Gadget')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/stock-transfers/index')
            ->has('transfers.data', 1)
            ->where('filters.search', 'Gadget')
        );

    // The destination warehouse code matches both transfers.
    $this->get('/acme/stock-transfers?search=B-01')
        ->assertInertia(fn (Assert $page) => $page->has('transfers.data', 2));

    $this->get('/acme/stock-transfers?search=Nope')
        ->assertInertia(fn (Assert $page) => $page->has('transfers.data', 0));
});
