<?php

use App\Models\Tenant;

it('serves a tenant route resolved by slug', function () {
    Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

    $this->get('/acme/_probe')
        ->assertOk()
        ->assertSee('acme');
});

it('does not treat a reserved slug (admin) as a tenant', function () {
    // The {tenant} pattern excludes reserved words, so /admin/_probe never
    // matches the tenant group and there is no central route for it -> 404.
    $this->get('/admin/_probe')->assertNotFound();
});

it('404s (not 500) an unknown slug that has no tenant', function () {
    $this->get('/ghost/_probe')->assertNotFound();
});
