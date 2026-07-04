<?php

declare(strict_types=1);

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
| PreventAccessFromCentralDomains is intentionally OMITTED: with path/slug
| identification the tenant shares the central host, so it would 404 all traffic.
|
*/

Route::middleware(['web', InitializeTenancyByPath::class])
    ->prefix('{tenant}')
    ->group(function () {
        // Throwaway smoke route — replaced by real tenant pages in Task 13.
        // The resolver forgets the {tenant} param, so read the tenant via tenant().
        Route::get('/_probe', fn () => tenant('slug'));
    });
