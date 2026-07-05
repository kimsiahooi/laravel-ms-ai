<?php

use App\Actions\ProvisionTenant;

it('logs a tenant user into their own tenant dashboard', function () {
    app(ProvisionTenant::class)->handle('Acme', 'acme', 'Jane', 'jane@acme.test', 'password');

    $this->from('/acme/login')
        ->post('/acme/login', ['email' => 'jane@acme.test', 'password' => 'password'])
        ->assertRedirect(route('tenant.dashboard', ['tenant' => 'acme']))
        ->assertSessionHasNoErrors();

    // A tenant login must never authenticate the central super-admin guard.
    $this->assertGuest('central');
});

it('does not let a user log into a tenant they do not belong to', function () {
    app(ProvisionTenant::class)->handle('Acme', 'acme', 'Jane', 'jane@acme.test', 'password');
    app(ProvisionTenant::class)->handle('Globex', 'globex', 'Greg', 'greg@globex.test', 'password');

    // Jane exists only in acme's database, not globex's.
    $this->from('/globex/login')
        ->post('/globex/login', ['email' => 'jane@acme.test', 'password' => 'password'])
        ->assertRedirect('/globex/login')
        ->assertSessionHasErrors('email');
});

it('redirects a guest from the tenant dashboard to that tenant login', function () {
    app(ProvisionTenant::class)->handle('Acme', 'acme', 'Jane', 'jane@acme.test', 'password');

    $this->get('/acme/dashboard')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('logs a tenant user out', function () {
    app(ProvisionTenant::class)->handle('Acme', 'acme', 'Jane', 'jane@acme.test', 'password');

    $this->post('/acme/login', ['email' => 'jane@acme.test', 'password' => 'password']);

    $this->post('/acme/logout')->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
    $this->assertGuest('web');
});
