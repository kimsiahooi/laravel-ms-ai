<?php

use App\Actions\ProvisionTenant;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

function tenantDatabaseExists(string $slug): bool
{
    $name = config('tenancy.database.prefix').$slug;

    // Not "SHOW DATABASES LIKE ?": Laravel disables PDO::ATTR_EMULATE_PREPARES
    // by default, and MySQL's native prepared-statement protocol rejects a
    // bound placeholder inside a SHOW statement. information_schema.SCHEMATA
    // is a normal SELECT, so binding works, with identical semantics.
    return DB::connection('central')->select(
        'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
        [$name],
    ) !== [];
}

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('keeps the tenant database when the tenant is soft deleted', function () {
    expect(tenantDatabaseExists('acme'))->toBeTrue();

    $this->tenant->delete();

    expect($this->tenant->trashed())->toBeTrue()
        ->and(tenantDatabaseExists('acme'))->toBeTrue();
});

it('drops the tenant database when the tenant is force deleted', function () {
    $this->tenant->forceDelete();

    expect(Tenant::withTrashed()->find('acme'))->toBeNull()
        ->and(tenantDatabaseExists('acme'))->toBeFalse();
});
