<?php

use App\Actions\ProvisionTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

it('provisions a tenant database and seeds exactly one admin user inside it', function () {
    $tenant = app(ProvisionTenant::class)->handle(
        name: 'Acme Co',
        slug: 'acme',
        adminName: 'Ada Admin',
        adminEmail: 'ada@acme.test',
        adminPassword: 'secret-password',
    );

    expect($tenant)->toBeInstanceOf(Tenant::class)
        ->and($tenant->slug)->toBe('acme')
        ->and($tenant->getConnectionName())->toBe('central');

    // The admin lives in the TENANT database, not central.
    $tenant->run(function () {
        expect(User::count())->toBe(1);

        $admin = User::first();
        expect($admin->email)->toBe('ada@acme.test')
            ->and($admin->name)->toBe('Ada Admin')
            ->and(Hash::check('secret-password', $admin->password))->toBeTrue();
    });

    // Nothing leaked into the central users table.
    expect(User::on('central')->where('email', 'ada@acme.test')->exists())->toBeFalse();
});

it('rejects a reserved slug before creating any database', function () {
    expect(fn () => app(ProvisionTenant::class)->handle(
        name: 'Admin Corp',
        slug: 'admin', // in ReservedSlugs::LIST
        adminName: 'X',
        adminEmail: 'x@x.test',
        adminPassword: 'secret-password',
    ))->toThrow(ValidationException::class);

    expect(Tenant::where('slug', 'admin')->exists())->toBeFalse();
});
