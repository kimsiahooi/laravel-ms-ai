<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

it('provisions a cache table inside each tenant database', function () {
    $tenant = Tenant::create(['name' => 'Acme', 'id' => 'acme']);

    $tenant->run(function () {
        expect(Schema::hasTable('cache'))->toBeTrue()
            ->and(Schema::hasTable('cache_locks'))->toBeTrue();
    });
});

it('lets the database cache store round-trip inside tenant context (no missing-table error)', function () {
    $tenant = Tenant::create(['name' => 'Acme', 'id' => 'acme']);

    // Reproduces the login rate limiter's failure: within tenancy the default
    // connection is the tenant DB, so the database cache store must find a cache
    // table there. Uses the database store explicitly (the test default is array).
    $tenant->run(function () {
        Cache::store('database')->put('probe', 'ok', 60);

        expect(Cache::store('database')->get('probe'))->toBe('ok');
    });
});
