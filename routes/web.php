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
        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('tenants/trashed', [TenantController::class, 'trashed'])->name('tenants.trashed');
        Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');
        Route::patch('tenants/{tenant}/restore', [TenantController::class, 'restore'])
            ->withTrashed()
            ->name('tenants.restore');
        Route::delete('tenants/{tenant}/force', [TenantController::class, 'forceDestroy'])
            ->withTrashed()
            ->name('tenants.force-destroy');
        Route::post('logout', [AdminSessionController::class, 'destroy'])->name('logout');
    });
});

require __DIR__.'/settings.php';
