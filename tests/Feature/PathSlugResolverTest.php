<?php

use App\Models\Tenant;
use App\Tenancy\PathSlugTenantResolver;
use Illuminate\Routing\Route;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByPathException;

it('resolves a tenant from the slug path segment', function () {
    $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

    $route = new Route(['GET'], '/{tenant}/dashboard', fn () => null);
    $route->bind(request());
    $route->setParameter('tenant', 'acme');

    $resolved = app(PathSlugTenantResolver::class)->resolve($route);

    expect($resolved->getTenantKey())->toBe($tenant->getTenantKey());
});

it('forgets the tenant route parameter after resolving (never leaked to controllers)', function () {
    Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

    $route = new Route(['GET'], '/{tenant}/dashboard', fn () => null);
    $route->bind(request());
    $route->setParameter('tenant', 'acme');

    app(PathSlugTenantResolver::class)->resolve($route);

    expect($route->parameter('tenant'))->toBeNull();
});

it('resolves a tenant whose slug is the falsy-looking string "0"', function () {
    $tenant = Tenant::create(['name' => 'Zero', 'slug' => '0']);

    $route = new Route(['GET'], '/{tenant}/dashboard', fn () => null);
    $route->bind(request());
    $route->setParameter('tenant', '0');

    $resolved = app(PathSlugTenantResolver::class)->resolve($route);

    expect($resolved->getTenantKey())->toBe($tenant->getTenantKey());
});

it('throws when the slug has no matching tenant', function () {
    $route = new Route(['GET'], '/{tenant}/dashboard', fn () => null);
    $route->bind(request());
    $route->setParameter('tenant', 'ghost');

    app(PathSlugTenantResolver::class)->resolve($route);
})->throws(TenantCouldNotBeIdentifiedByPathException::class);
