<?php

use App\Actions\ProvisionTenant;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme',
        'acme',
        'Ada',
        'ada@acme.test',
        'password123',
    );
});

it('redirects a guest from the tenant root to the tenant login', function () {
    $this->get('/acme')->assertRedirect('/acme/login');
});

it('redirects an authenticated tenant user from the tenant root to the dashboard', function () {
    $this->post('/acme/login', [
        'email' => 'ada@acme.test',
        'password' => 'password123',
    ]);

    $this->get('/acme')->assertRedirect('/acme/dashboard');
});

it('404s the root of a non-existent tenant', function () {
    $this->get('/nope')->assertNotFound();
});
