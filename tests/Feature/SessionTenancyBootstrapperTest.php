<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

it('scopes the session cookie name per tenant and reverts on end', function () {
    $original = config('session.cookie');

    $tenant = Tenant::create(['name' => 'Acme', 'id' => 'acme']);

    tenancy()->initialize($tenant);
    expect(config('session.cookie'))->toBe($original.'_tenant_'.$tenant->getTenantKey());

    tenancy()->end();
    expect(config('session.cookie'))->toBe($original);
});

it('gives two tenants distinct session cookie names', function () {
    $original = config('session.cookie');

    $a = Tenant::create(['name' => 'A', 'id' => 'a']);
    $b = Tenant::create(['name' => 'B', 'id' => 'b']);

    tenancy()->initialize($a);
    $cookieA = config('session.cookie');
    tenancy()->end();

    tenancy()->initialize($b);
    $cookieB = config('session.cookie');
    tenancy()->end();

    expect($cookieA)->not->toBe($cookieB)
        ->and($cookieA)->not->toBe($original)
        ->and($cookieB)->not->toBe($original);
});

it('provisions a sessions table inside each tenant database', function () {
    $tenant = Tenant::create(['name' => 'Acme', 'id' => 'acme']);

    $tenant->run(function () {
        expect(Schema::hasTable('sessions'))->toBeTrue();
    });
});
