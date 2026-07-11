<?php

use App\Actions\ProvisionTenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('redirects a guest from the dashboard to the tenant login', function () {
    $this->get('/acme/dashboard')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('shows the logged-in user and organization details', function () {
    loginAsAcmeUser();

    $this->get('/acme/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/dashboard')
            // organization = the tenant (name/slug shared, plus dashboard-only details)
            ->where('organization.name', 'Acme')
            ->where('organization.slug', 'acme')
            ->where('organization.members', 1) // only Ada exists in the tenant DB
            ->has('organization.logo')         // present (null until a logo is set)
            ->has('organization.created_at')
            // logged-in user comes from the globally-shared auth prop
            ->where('auth.user.email', 'ada@acme.test')
            ->where('auth.user.name', 'Ada')
        );
});
