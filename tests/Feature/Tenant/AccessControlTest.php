<?php

use App\Actions\ProvisionTenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('lets a member reach a screen they have view access to', function () {
    loginAsAcmeMember(['suppliers.view']);

    $this->get('/acme/suppliers')->assertOk();
});

it('403s a member on a screen they lack access to', function () {
    loginAsAcmeMember(['suppliers.view']);

    $this->get('/acme/customers')->assertForbidden();
});

it('403s a member creating on a screen they can only view', function () {
    loginAsAcmeMember(['suppliers.view']);

    $this->post('/acme/suppliers', ['name' => 'Nope'])->assertForbidden();
});

it('gates the Users screen behind users.view', function () {
    loginAsAcmeMember(['suppliers.view']);

    $this->get('/acme/users')->assertForbidden();
});

it('gates business settings behind its permissions', function () {
    loginAsAcmeMember(['suppliers.view']);

    $this->get('/acme/settings/business')->assertForbidden();
    $this->put('/acme/settings/business', [])->assertForbidden();
});

it('keeps unmapped routes open to any signed-in user', function () {
    loginAsAcmeMember([]);

    // The dashboard has no permission mapping — every signed-in user can see it.
    $this->get('/acme/dashboard')->assertOk();
});

it('lets the Administrator reach every gated screen', function () {
    loginAsAcmeUser();

    $this->get('/acme/users')->assertOk();
    $this->get('/acme/roles')->assertOk();
    $this->get('/acme/settings/business')->assertOk();
    $this->get('/acme/customers')->assertOk();
});

it('renders the friendly error page on a 403', function () {
    loginAsAcmeMember(['suppliers.view']);

    $this->get('/acme/customers')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error')
            ->where('status', 403)
            ->where('homeUrl', '/acme/dashboard')
            ->where('homeLabel', 'Back to dashboard')
        );
});

it('shares the Administrator’s permissions + is_admin flag to the front-end', function () {
    loginAsAcmeUser();

    $this->get('/acme/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.is_admin', true)
            ->where('auth.permissions', fn ($permissions) => count($permissions) > 0)
        );
});

it('shares a member’s limited permissions, not the admin flag', function () {
    loginAsAcmeMember(['suppliers.view']);

    $this->get('/acme/suppliers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.is_admin', false)
            ->where('auth.permissions', ['suppliers.view'])
        );
});
