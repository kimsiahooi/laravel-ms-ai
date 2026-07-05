<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

it('provisions a passkeys table inside each tenant database', function () {
    $tenant = Tenant::create(['name' => 'Acme', 'id' => 'acme']);

    $tenant->run(function () {
        expect(Schema::hasTable('passkeys'))->toBeTrue();
    });
});

it('keeps a passkeys table on the central connection too', function () {
    expect(Schema::connection('central')->hasTable('passkeys'))->toBeTrue();
});
