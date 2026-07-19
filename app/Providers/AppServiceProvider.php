<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Setting;
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
     * Alias polymorphic morph types so `*_type` columns store short, stable keys instead
     * of FQCNs: `product` / `raw_material` for the stock tables, and `setting` for the
     * media parent (a settings file/logo attaches to a Setting row) so `media.model_type`
     * reads `setting` — matching `product` — and stays traceable/filterable by source.
     * Non-enforcing so other morphs (e.g. passkeys, the activity log) are unaffected.
     */
    protected function configureMorphMap(): void
    {
        Relation::morphMap([
            'product' => Product::class,
            'raw_material' => RawMaterial::class,
            'setting' => Setting::class,
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
