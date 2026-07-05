<?php

use App\Models\CentralUser;
use App\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'password',
    ]);
});

it('paginates the tenants list and exposes per-page', function () {
    makeTenants(12);

    $this->actingAs($this->admin, 'central')
        ->get('/admin/tenants?per_page=10')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/tenants/index')
            ->has('tenants.data', 10)
            ->where('tenants.total', 12)
            ->where('tenants.per_page', 10)
            ->where('tenants.last_page', 2)
            ->where('filters.per_page', 10)
        );
});

it('returns the requested page', function () {
    makeTenants(12);

    $this->actingAs($this->admin, 'central')
        ->get('/admin/tenants?per_page=10&page=2')
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 2)
            ->where('tenants.current_page', 2)
        );
});

it('filters the paginated tenants by search (name or slug)', function () {
    Tenant::withoutEvents(function () {
        Tenant::create(['name' => 'Acme Manufacturing', 'id' => 'acme']);
        Tenant::create(['name' => 'Globex', 'id' => 'globex']);
    });

    $this->actingAs($this->admin, 'central')
        ->get('/admin/tenants?search=acme')
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 1)
            ->where('tenants.data.0.slug', 'acme')
            ->where('tenants.total', 1)
            ->where('filters.search', 'acme')
        );
});

it('clamps an out-of-range per_page back to the default', function () {
    makeTenants(1);

    $this->actingAs($this->admin, 'central')
        ->get('/admin/tenants?per_page=999')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenants.per_page', 10)
            ->where('filters.per_page', 10)
        );
});

it('redirects a guest away from the tenants index', function () {
    $this->get('/admin/tenants')->assertRedirect('/admin/login');
});
