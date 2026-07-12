<?php

use App\Actions\ProvisionTenant;
use App\Models\BomItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\StockTake;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;

it('seeds a coherent Malaysia/Singapore sample dataset when opted in', function () {
    $tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
        seedDemoData: true,
    );

    $tenant->run(function () {
        // Normal tables: at least 3 rows each.
        expect(Category::count())->toBeGreaterThanOrEqual(3)
            ->and(Product::count())->toBeGreaterThanOrEqual(3)
            ->and(BomItem::count())->toBeGreaterThanOrEqual(3)
            ->and(Warehouse::count())->toBeGreaterThanOrEqual(3)
            ->and(WarehouseStock::count())->toBeGreaterThanOrEqual(3)
            ->and(StockMovement::count())->toBeGreaterThanOrEqual(3)
            ->and(StockTransfer::count())->toBeGreaterThanOrEqual(3);

        // Status tables: 1–2 rows, each with one row in its terminal status.
        expect(PurchaseOrder::count())->toBeLessThanOrEqual(2)
            ->and(PurchaseOrder::where('status', 'received')->count())->toBe(1)
            ->and(SalesOrder::where('status', 'fulfilled')->count())->toBe(1)
            ->and(ProductionOrder::where('status', 'completed')->count())->toBe(1)
            ->and(StockTake::where('status', 'posted')->count())->toBe(1)
            ->and(PurchaseReturn::where('status', 'completed')->count())->toBe(1)
            ->and(SalesReturn::where('status', 'completed')->count())->toBe(1);

        // Stock stayed consistent (never driven negative) and no extra users.
        expect(WarehouseStock::where('quantity', '<', 0)->count())->toBe(0)
            ->and(User::count())->toBe(1);
    });
});

it('leaves a tenant empty when sample data is not requested', function () {
    $tenant = app(ProvisionTenant::class)->handle(
        'Beta', 'beta', 'Ben', 'ben@beta.test', 'password123',
    );

    $tenant->run(function () {
        expect(Product::count())->toBe(0)
            ->and(PurchaseOrder::count())->toBe(0)
            ->and(User::count())->toBe(1);
    });
});
