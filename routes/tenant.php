<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\ActivityController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\ExportController;
use App\Http\Controllers\Tenant\LocationController;
use App\Http\Controllers\Tenant\MediaController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ProductionOrderController;
use App\Http\Controllers\Tenant\PurchaseOrderController;
use App\Http\Controllers\Tenant\PurchaseReturnController;
use App\Http\Controllers\Tenant\RawMaterialController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SalesOrderController;
use App\Http\Controllers\Tenant\SalesReturnController;
use App\Http\Controllers\Tenant\SessionController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Controllers\Tenant\StockLookupController;
use App\Http\Controllers\Tenant\StockMovementController;
use App\Http\Controllers\Tenant\StockTakeController;
use App\Http\Controllers\Tenant\StockTransferController;
use App\Http\Controllers\Tenant\SupplierController;
use App\Http\Controllers\Tenant\WarehouseController;
use App\Http\Controllers\Tenant\WarehouseReorderLevelController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

/*
|--------------------------------------------------------------------------
| Tenant Routes (path-identified by slug)
|--------------------------------------------------------------------------
|
| Loaded once by App\Providers\TenancyServiceProvider::mapRoutes(), which wraps
| this file only in Route::namespace('')->group(...) — it adds NO middleware and
| NO prefix, so this group declares both itself. Do NOT also register this file
| in bootstrap/app.php (that would double-register every route).
|
| `{tenant}` MUST be the FIRST route parameter of every route here, or
| InitializeTenancyByPath throws RouteIsMissingTenantParameterException. Its
| allowed values are constrained by the Route::pattern('tenant', ...) declared in
| AppServiceProvider so reserved/central words are never resolved as tenants.
|
| The resolver forgets the {tenant} param, so redirect()->route('tenant.*') calls
| must pass ['tenant' => tenant('id')] explicitly (the id column stores the slug).
|
| PreventAccessFromCentralDomains is intentionally OMITTED: with path/slug
| identification the tenant shares the central host, so it would 404 all traffic.
|
*/

Route::middleware(['web', InitializeTenancyByPath::class])
    ->prefix('{tenant}')
    ->name('tenant.')
    ->group(function () {
        // Throwaway smoke route (kept for the reserved/unknown-slug route tests).
        Route::get('/_probe', fn () => tenant('id'));

        // Bare /{tenant} -> the dashboard when signed in, otherwise the login page.
        // Pass ['tenant' => tenant('id')] since the resolver forgets the param.
        Route::get('/', fn () => redirect()->route(
            Auth::guard('web')->check() ? 'tenant.dashboard' : 'tenant.login',
            ['tenant' => tenant('id')],
        ))->name('home');

        Route::middleware('guest:web')->group(function () {
            Route::get('login', [SessionController::class, 'create'])->name('login');
            Route::post('login', [SessionController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('login.store');
        });

        Route::middleware('auth:web')->group(function () {
            Route::get('dashboard', DashboardController::class)->name('dashboard');

            // Catalog
            Route::resource('categories', CategoryController::class)
                ->only(['index', 'store', 'update', 'destroy']);
            Route::resource('suppliers', SupplierController::class)
                ->only(['index', 'store', 'update', 'destroy']);
            Route::resource('customers', CustomerController::class)
                ->only(['index', 'store', 'update', 'destroy']);
            Route::resource('raw-materials', RawMaterialController::class)
                ->parameters(['raw-materials' => 'rawMaterial'])
                ->only(['index', 'store', 'update', 'destroy']);
            Route::resource('products', ProductController::class)
                ->only(['index', 'store', 'update', 'destroy']);
            // A product's BOM.
            Route::put('products/{product}/bom', [ProductController::class, 'updateBom'])
                ->name('products.bom');
            // Location (site) is the top of the inventory hierarchy — register it
            // first; warehouses live under a location.
            Route::resource('locations', LocationController::class)
                ->only(['index', 'store', 'update', 'destroy']);
            Route::resource('warehouses', WarehouseController::class)
                ->only(['index', 'store', 'update', 'destroy', 'show']);
            Route::put('warehouses/{warehouse}/reorder-levels', [WarehouseReorderLevelController::class, 'update'])
                ->name('warehouses.reorder-levels.update');

            // Inventory ledger — append-only, so only list + create (no edit/delete).
            Route::get('stock-movements', [StockMovementController::class, 'index'])
                ->name('stock-movements.index');
            Route::post('stock-movements', [StockMovementController::class, 'store'])
                ->name('stock-movements.store');

            Route::get('stock-transfers', [StockTransferController::class, 'index'])
                ->name('stock-transfers.index');
            Route::post('stock-transfers', [StockTransferController::class, 'store'])
                ->name('stock-transfers.store');

            // On-hand lookup for the movement/transfer dialogs (read-only JSON).
            Route::get('stock/on-hand', [StockLookupController::class, 'onHand'])
                ->name('stock.on-hand');

            // Stock take — physical count that posts variance adjustments.
            Route::resource('stock-takes', StockTakeController::class)
                ->parameters(['stock-takes' => 'stockTake'])
                ->only(['index', 'show', 'store', 'destroy']);
            Route::post('stock-takes/{stockTake}/post', [StockTakeController::class, 'post'])
                ->name('stock-takes.post');
            Route::post('stock-takes/{stockTake}/cancel', [StockTakeController::class, 'cancel'])
                ->name('stock-takes.cancel');

            // Orders
            Route::resource('purchase-orders', PurchaseOrderController::class)
                ->parameters(['purchase-orders' => 'purchaseOrder'])
                ->only(['index', 'show', 'store', 'update', 'destroy']);
            Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])
                ->name('purchase-orders.receive');
            Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
                ->name('purchase-orders.cancel');

            // Purchase returns — send received raw materials back (stock OUT).
            Route::resource('purchase-returns', PurchaseReturnController::class)
                ->parameters(['purchase-returns' => 'purchaseReturn'])
                ->only(['index', 'show', 'store', 'update', 'destroy']);
            Route::post('purchase-returns/{purchaseReturn}/complete', [PurchaseReturnController::class, 'complete'])
                ->name('purchase-returns.complete');
            Route::post('purchase-returns/{purchaseReturn}/cancel', [PurchaseReturnController::class, 'cancel'])
                ->name('purchase-returns.cancel');

            Route::resource('sales-orders', SalesOrderController::class)
                ->parameters(['sales-orders' => 'salesOrder'])
                ->only(['index', 'show', 'store', 'update', 'destroy']);
            Route::post('sales-orders/{salesOrder}/fulfill', [SalesOrderController::class, 'fulfill'])
                ->name('sales-orders.fulfill');
            Route::post('sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])
                ->name('sales-orders.cancel');

            // Sales returns — take products back from a customer (stock IN).
            Route::resource('sales-returns', SalesReturnController::class)
                ->parameters(['sales-returns' => 'salesReturn'])
                ->only(['index', 'show', 'store', 'update', 'destroy']);
            Route::post('sales-returns/{salesReturn}/complete', [SalesReturnController::class, 'complete'])
                ->name('sales-returns.complete');
            Route::post('sales-returns/{salesReturn}/cancel', [SalesReturnController::class, 'cancel'])
                ->name('sales-returns.cancel');

            // Manufacturing — make a product by consuming its BOM.
            Route::resource('production-orders', ProductionOrderController::class)
                ->parameters(['production-orders' => 'productionOrder'])
                ->only(['index', 'show', 'store', 'destroy']);
            Route::post('production-orders/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])
                ->name('production-orders.complete');
            Route::post('production-orders/{productionOrder}/cancel', [ProductionOrderController::class, 'cancel'])
                ->name('production-orders.cancel');

            // Serve any tenant media by id at one content-addressed, extension-less URL
            // (ends in `/media/{id}`, not `.png`/`.jpg`/…). Some nginx setups (e.g.
            // CloudPanel) serve extension URLs straight from the docroot with
            // `try_files $uri =404` and never reach Laravel; an extension-less path avoids
            // that. The id changes on every re-upload (singleFile → new row → new id), so a
            // stored URL is never stale and a deleted id 404s. Every media <img> in the app
            // (product images, the settings logo) is served here.
            Route::get('media/{media}', MediaController::class)->name('media');

            // Traceability — a read-only history of catalog/order changes.
            Route::get('activity', [ActivityController::class, 'index'])
                ->name('activity.index');

            // Period-scoped reports (sales, purchases, production, movements, low stock).
            Route::get('reports', [ReportController::class, 'index'])
                ->name('reports.index');

            // CSV / Excel export for any registered list resource.
            Route::get('export/{resource}', [ExportController::class, 'download'])
                ->name('export');

            // Settings — schema-driven, one route per category (business now; more
            // later). The controller resolves the category via SettingsRegistry.
            Route::get('settings/{category}', [SettingsController::class, 'edit'])
                ->name('settings.edit');
            Route::put('settings/{category}', [SettingsController::class, 'update'])
                ->name('settings.update');
            // The logo (a file-typed setting) streams through the generic media route
            // above — no per-setting file endpoint.

            Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
        });
    });
