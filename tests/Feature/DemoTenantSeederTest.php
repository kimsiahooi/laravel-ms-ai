<?php

use App\Actions\ProvisionTenant;
use App\Models\BomItem;
use App\Models\Category;
use App\Models\Customer;
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
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;

it('seeds a fuller Malaysia/Singapore sample dataset when opted in', function () {
    $tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
        seedDemoData: true,
    );

    $tenant->run(function () {
        // Catalog + sites populated (sites stay realistic, not ~20).
        expect(Category::count())->toBeGreaterThanOrEqual(8)
            ->and(Warehouse::count())->toBeGreaterThanOrEqual(5)
            ->and(BomItem::count())->toBeGreaterThanOrEqual(3)
            ->and(StockTransfer::count())->toBeGreaterThanOrEqual(15);

        // List-heavy tables: ~20 rows each (assert a comfortable floor).
        expect(Supplier::count())->toBeGreaterThanOrEqual(15)
            ->and(Customer::count())->toBeGreaterThanOrEqual(15)
            ->and(RawMaterial::count())->toBeGreaterThanOrEqual(15)
            ->and(Product::count())->toBeGreaterThanOrEqual(15)
            ->and(PurchaseOrder::count())->toBeGreaterThanOrEqual(15)
            ->and(SalesOrder::count())->toBeGreaterThanOrEqual(15)
            ->and(ProductionOrder::count())->toBeGreaterThanOrEqual(15)
            ->and(PurchaseReturn::count())->toBeGreaterThanOrEqual(15)
            ->and(SalesReturn::count())->toBeGreaterThanOrEqual(15)
            ->and(StockTake::count())->toBeGreaterThanOrEqual(15);

        // Every status has at least one row (incl. cancelled / draft).
        foreach (['pending', 'received', 'cancelled'] as $s) {
            expect(PurchaseOrder::where('status', $s)->count())->toBeGreaterThanOrEqual(1);
        }
        foreach (['pending', 'fulfilled', 'cancelled'] as $s) {
            expect(SalesOrder::where('status', $s)->count())->toBeGreaterThanOrEqual(1);
        }
        foreach (['pending', 'completed', 'cancelled'] as $s) {
            expect(ProductionOrder::where('status', $s)->count())->toBeGreaterThanOrEqual(1)
                ->and(PurchaseReturn::where('status', $s)->count())->toBeGreaterThanOrEqual(1)
                ->and(SalesReturn::where('status', $s)->count())->toBeGreaterThanOrEqual(1);
        }
        foreach (['draft', 'posted', 'cancelled'] as $s) {
            expect(StockTake::where('status', $s)->count())->toBeGreaterThanOrEqual(1);
        }

        // Dates are spread out, so newest-first sorting is meaningful and the dashboard
        // charts vary rather than clustering at seed time.
        expect(PurchaseOrder::query()->distinct()->count('created_at'))->toBeGreaterThanOrEqual(10)
            ->and(StockMovement::query()->distinct()->count('created_at'))->toBeGreaterThanOrEqual(5);

        // created_at rises with the id, so the newest-first list reads as a clean
        // descending run of order numbers (not the shuffle random dates produced).
        $timestamps = PurchaseOrder::orderBy('id')->pluck('created_at')
            ->map(fn ($when) => $when->getTimestamp())->all();
        $ascending = $timestamps;
        sort($ascending);
        expect($timestamps)->toBe($ascending);

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
