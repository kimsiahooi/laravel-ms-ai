<?php

declare(strict_types=1);
use App\Support\Media\TenantPathGenerator;
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

it('points medialibrary at the private assets disk via the tenant path generator', function () {
    // Files must land on the private `assets` disk and be slug-namespaced by our
    // generator — the whole tenant-isolation + teardown story depends on it.
    expect(config('media-library.disk_name'))->toBe('assets')
        ->and(config('media-library.path_generator'))
        ->toBe(TenantPathGenerator::class);
});

it('never defines a database connection named "tenant" (central is the landlord)', function () {
    // CLAUDE.md: the central connection is "central"; a connection literally
    // named "tenant" must never exist.
    $connections = config('database.connections');

    expect($connections)->toBeArray()
        ->and($connections)->toHaveKey('central')
        ->and($connections)->not->toHaveKey('tenant');
});
