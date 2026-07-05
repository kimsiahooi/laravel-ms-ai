<?php

use App\Models\Tenant;
use Illuminate\Routing\Route;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByPathException;
use Stancl\Tenancy\Resolvers\PathTenantResolver;

// The slug is the tenant key, so stancl's DEFAULT PathTenantResolver resolves the
// {tenant} path segment natively (no custom resolver) by finding on the key.

it('resolves a tenant from the {tenant} slug path segment', function () {
    $tenant = Tenant::create(['name' => 'Acme', 'id' => 'acme']);

    $route = new Route(['GET'], '/{tenant}/dashboard', fn () => null);
    $route->bind(request());
    $route->setParameter('tenant', 'acme');

    $resolved = app(PathTenantResolver::class)->resolve($route);

    expect($resolved->getTenantKey())->toBe($tenant->getTenantKey());
});

it('forgets the tenant route parameter after resolving (never leaked to controllers)', function () {
    Tenant::create(['name' => 'Acme', 'id' => 'acme']);

    $route = new Route(['GET'], '/{tenant}/dashboard', fn () => null);
    $route->bind(request());
    $route->setParameter('tenant', 'acme');

    app(PathTenantResolver::class)->resolve($route);

    expect($route->parameter('tenant'))->toBeNull();
});

it('throws when the slug has no matching tenant', function () {
    $route = new Route(['GET'], '/{tenant}/dashboard', fn () => null);
    $route->bind(request());
    $route->setParameter('tenant', 'ghost');

    app(PathTenantResolver::class)->resolve($route);
})->throws(TenantCouldNotBeIdentifiedByPathException::class);
