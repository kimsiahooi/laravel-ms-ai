<?php

use App\Http\Controllers\Central\AdminSessionController;
use App\Http\Controllers\Central\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// Central super-admin area. 'admin' is a reserved slug, so /admin/* lands here
// (not the tenant group). Namespaced route names (admin.*) avoid colliding with
// Fortify's root auth routes.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:central')->group(function () {
        Route::get('login', [AdminSessionController::class, 'create'])->name('login');
        Route::post('login', [AdminSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:central')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::post('logout', [AdminSessionController::class, 'destroy'])->name('logout');
    });
});

require __DIR__.'/settings.php';
