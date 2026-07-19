<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The custom central (/admin, guard 'central') and tenant (/{slug}, guard
        // 'web') controllers are the ONLY auth entrypoints. Fortify owns the passkey
        // routes and already calls LaravelPasskeys::ignoreRoutes() internally, so this
        // single call removes every bare Fortify + passkey route (login, register,
        // logout, password reset, email verification, two-factor, passkeys, ...).
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        //
    }
}
