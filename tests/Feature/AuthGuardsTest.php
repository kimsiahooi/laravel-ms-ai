<?php

use App\Models\CentralUser;
use App\Models\User;

it('pins central users to the central connection and the users table', function () {
    expect((new CentralUser)->getConnectionName())->toBe(config('tenancy.database.central_connection'))
        ->and((new CentralUser)->getTable())->toBe('users');
});

it('registers separate web (tenant) and central auth guards', function () {
    expect(config('auth.guards.web.provider'))->toBe('tenant_users')
        ->and(config('auth.guards.central.provider'))->toBe('central_users')
        ->and(config('auth.providers.tenant_users.model'))->toBe(User::class)
        ->and(config('auth.providers.central_users.model'))->toBe(CentralUser::class);
});
