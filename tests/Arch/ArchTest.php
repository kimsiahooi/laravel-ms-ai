<?php

declare(strict_types=1);
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Setting;
use App\Support\Media\TenantPathGenerator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/*
|--------------------------------------------------------------------------
| Architecture tests
|--------------------------------------------------------------------------
|
| Mechanical house rules (CLAUDE.md / docs/CODING-STANDARDS.md) enforced
| deterministically — these run in the normal Pest gate for free, so they
| never cost review tokens and catch regressions the moment they land.
|
*/

arch('app code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debug helpers are left in app code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'ds'])
    ->not->toBeUsed();

arch('DTOs are spatie laravel-data objects')
    ->expect('App\Data')
    ->toExtend('Spatie\LaravelData\Data');

arch('enums are real enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('form requests extend the framework FormRequest')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('the tenant media path generator implements the medialibrary contract')
    ->expect(TenantPathGenerator::class)
    ->toImplement(PathGenerator::class);

// Media must be streamed through the StreamsMedia concern (which sets cache
// validators so a re-upload is never stale), never raw Storage in a controller —
// so future file/image endpoints stay fresh by construction.
arch('tenant controllers never touch the Storage facade directly')
    ->expect('App\Http\Controllers\Tenant')
    ->not->toUse('Illuminate\Support\Facades\Storage');

it('points medialibrary at the private assets disk via the tenant path generator', function () {
    // Files must land on the private `assets` disk and be slug-namespaced by our
    // generator — the whole tenant-isolation + teardown story depends on it.
    expect(config('media-library.disk_name'))->toBe('assets')
        ->and(config('media-library.path_generator'))
        ->toBe(TenantPathGenerator::class)
        // No queue worker runs; conversions must stay inline or they'd silently
        // pile up in the `jobs` table and never process.
        ->and(config('media-library.queue_conversions_by_default'))->toBeFalse();
});

it('aliases the media + stock morph types so *_type columns store short keys', function () {
    // model_type/*_type store these short aliases instead of FQCNs, so media stays
    // traceable/filterable by source (product vs setting) and the stock tables stay
    // stable. Asserted so an alias can't be dropped without a failing test.
    expect(Relation::morphMap())->toMatchArray([
        'product' => Product::class,
        'raw_material' => RawMaterial::class,
        'setting' => Setting::class,
    ]);
});

it('registers a morph alias for every model that stores media (guards future models)', function () {
    // Auto-discovered guardrail: any App\Models model using InteractsWithMedia writes its
    // getMorphClass() into media.model_type, so each MUST have a morph-map alias — keeping
    // that column a short, stable, source-traceable key instead of an FQCN. Add a new media
    // model and forget to alias it (AppServiceProvider::configureMorphMap) and this fails,
    // naming the class — so you can't forget without the gate catching it.
    $aliased = array_flip(Relation::morphMap()); // FQCN => alias

    $mediaModels = collect(glob(app_path('Models/*.php')))
        ->map(fn (string $path): string => 'App\\Models\\'.pathinfo($path, PATHINFO_FILENAME))
        ->filter(fn (string $class): bool => class_exists($class)
            && in_array(InteractsWithMedia::class, class_uses_recursive($class), true))
        ->values();

    // Sanity: discovery actually resolved the media models (Product, Setting, …).
    expect($mediaModels)->not->toBeEmpty();

    // Each listed class here writes an un-aliased FQCN into media.model_type — add it to
    // AppServiceProvider::configureMorphMap(). Empty = every media model is aliased.
    $missing = $mediaModels
        ->reject(fn (string $class): bool => array_key_exists($class, $aliased))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('never defines a database connection named "tenant" (central is the landlord)', function () {
    // CLAUDE.md: the central connection is "central"; a connection literally
    // named "tenant" must never exist.
    $connections = config('database.connections');

    expect($connections)->toBeArray()
        ->and($connections)->toHaveKey('central')
        ->and($connections)->not->toHaveKey('tenant');
});
