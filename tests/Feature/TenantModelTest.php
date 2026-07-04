<?php

use App\Models\Tenant;

it('creates a tenant on the central connection with a stable integer id and slug', function () {
    $tenant = Tenant::create(['name' => 'Acme Co', 'slug' => 'acme']);

    expect($tenant->getConnectionName())->toBe('central')
        ->and($tenant->getKeyName())->toBe('id')
        ->and($tenant->id)->toBeInt()
        ->and($tenant->slug)->toBe('acme')
        ->and($tenant->name)->toBe('Acme Co')
        ->and($tenant->getTable())->toBe('tenants');
});

it('keeps name and slug as real, queryable columns (not folded into the data json)', function () {
    Tenant::create(['name' => 'Acme Co', 'slug' => 'acme']);

    $found = Tenant::where('slug', 'acme')->first();

    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Acme Co');
});

it('soft-deletes and restores a tenant (deleted_at is a real column)', function () {
    $tenant = Tenant::create(['name' => 'Acme Co', 'slug' => 'acme']);

    $tenant->delete();
    expect(Tenant::find($tenant->id))->toBeNull()
        ->and(Tenant::withTrashed()->find($tenant->id))->not->toBeNull();

    $tenant->restore();
    expect(Tenant::find($tenant->id)?->slug)->toBe('acme');
});
