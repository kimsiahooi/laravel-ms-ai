<?php

use App\Http\Controllers\Central\AdminSessionController;
use App\Http\Controllers\Central\DashboardController;
use App\Http\Controllers\Central\TenantController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Legacy starter dashboard (web guard, currently unreachable — superseded by the
// central/tenant dashboards). Kept so the starter app shell still resolves; the
// real shells are built in the UI/UX phase.
Route::inertia('dashboard', 'dashboard')->middleware(['auth', 'verified'])->name('dashboard');

// Central super-admin area. 'admin' is a reserved slug, so /admin/* lands here
// (not the tenant group). Namespaced route names (admin.*) avoid colliding with
// Fortify's root auth routes.
Route::prefix('admin')->name('admin.')->group(function () {
    // Bare /admin -> the dashboard when signed in, otherwise the login page.
    Route::get('/', fn () => redirect()->route(
        Auth::guard('central')->check() ? 'admin.dashboard' : 'admin.login'
    ))->name('home');

    Route::middleware('guest:central')->group(function () {
        Route::get('login', [AdminSessionController::class, 'create'])->name('login');
        Route::post('login', [AdminSessionController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('login.store');
    });

    Route::middleware('auth:central')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::prefix('tenants')->name('tenants.')->group(function () {
            Route::get('/', [TenantController::class, 'index'])->name('index');
            Route::post('/', [TenantController::class, 'store'])->name('store');
            Route::get('trashed', [TenantController::class, 'trashed'])->name('trashed');
            Route::delete('{tenant}', [TenantController::class, 'destroy'])->name('destroy');
            Route::patch('{tenant}/restore', [TenantController::class, 'restore'])
                ->withTrashed()
                ->name('restore');
            Route::delete('{tenant}/force', [TenantController::class, 'forceDestroy'])
                ->withTrashed()
                ->name('force-destroy');
        });
        Route::post('logout', [AdminSessionController::class, 'destroy'])->name('logout');
    });
});

require __DIR__.'/settings.php';
