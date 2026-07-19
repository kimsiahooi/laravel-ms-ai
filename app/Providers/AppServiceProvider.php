<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Support\ReservedSlugs;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureTenancyRouting();
        $this->configureMorphMap();
    }

    /**
     * Alias the stockable morph types so `stock_movements`/`location_stocks` store
     * short, stable keys (`product` / `raw_material`) instead of FQCNs. Non-enforcing
     * so other morphs (e.g. passkeys) are unaffected.
     */
    protected function configureMorphMap(): void
    {
        Relation::morphMap([
            'product' => Product::class,
            'raw_material' => RawMaterial::class,
        ]);
    }

    /**
     * Constrain the {tenant} path segment to real, non-reserved slugs, and turn
     * an unresolvable tenant slug into a clean 404 instead of the default 500.
     */
    protected function configureTenancyRouting(): void
    {
        Route::pattern('tenant', ReservedSlugs::pattern());

        InitializeTenancyByPath::$onFail = fn ($e, $request, $next) => abort(404);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
