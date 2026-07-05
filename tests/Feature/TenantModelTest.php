<?php

use App\Models\Tenant;

it('creates a tenant on the central connection keyed by its id (the slug)', function () {
    $tenant = Tenant::create(['name' => 'Acme Co', 'id' => 'acme']);

    expect($tenant->getConnectionName())->toBe('central')
        ->and($tenant->getKeyName())->toBe('id')
        ->and($tenant->getTenantKeyName())->toBe('id')
        ->and($tenant->getTenantKey())->toBe('acme')
        ->and($tenant->getKey())->toBe('acme')
        ->and($tenant->getIncrementing())->toBeFalse()
        ->and($tenant->getKeyType())->toBe('string')
        ->and($tenant->name)->toBe('Acme Co')
        ->and($tenant->getTable())->toBe('tenants');
});

it('names the tenant database after the id/slug (prefix + id)', function () {
    $tenant = Tenant::create(['name' => 'Acme Co', 'id' => 'acme']);

    expect($tenant->database()->getName())
        ->toBe(config('tenancy.database.prefix').'acme');
});

it('keeps name as a real, queryable column (not folded into the data json)', function () {
    Tenant::create(['name' => 'Acme Co', 'id' => 'acme']);

    $found = Tenant::where('id', 'acme')->first();

    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Acme Co');
});

it('soft-deletes and restores a tenant (deleted_at is a real column)', function () {
    $tenant = Tenant::create(['name' => 'Acme Co', 'id' => 'acme']);

    $tenant->delete();
    expect(Tenant::find('acme'))->toBeNull()
        ->and(Tenant::withTrashed()->find('acme'))->not->toBeNull();

    $tenant->restore();
    expect(Tenant::find('acme')?->name)->toBe('Acme Co');
});
