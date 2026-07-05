<?php

use App\Models\Tenant;

it('serves a tenant route resolved by slug', function () {
    Tenant::create(['name' => 'Acme', 'id' => 'acme']);

    $this->get('/acme/_probe')
        ->assertOk()
        ->assertSee('acme');
});

it('does not treat a reserved slug (admin) as a tenant, even when such a tenant row exists', function () {
    // Create a tenant whose slug IS the reserved word. If the {tenant} pattern
    // regressed and let 'admin' through, InitializeTenancyByPath would resolve
    // THIS tenant and /admin/_probe would return 200 "admin". Because the pattern
    // excludes 'admin', the group never matches -> 404. The existing tenant row is
    // what makes the pattern the ONLY thing that can produce this 404 (without it,
    // $onFail would also 404 and a pattern regression would go unnoticed).
    Tenant::create(['name' => 'Admin', 'id' => 'admin']);

    $this->get('/admin/_probe')->assertNotFound();
});

it('404s (not 500) an unknown slug that has no tenant', function () {
    $this->get('/ghost/_probe')->assertNotFound();
});
