<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\RawMaterialController;
use App\Http\Controllers\Tenant\SessionController;
use App\Http\Controllers\Tenant\SupplierController;
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

            Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
        });
    });
