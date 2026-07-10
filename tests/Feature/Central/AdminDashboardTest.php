<?php

use App\Models\CentralUser;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'password',
    ]);
});

it('renders the dashboard with stats and no tenants list', function () {
    makeTenants(3);

    $this->actingAs($this->admin, 'central')
        ->get('/admin/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('stats.total', 3)
            ->has('signups', 30)
            ->missing('tenants')
        );
});

it('redirects a guest away from the dashboard', function () {
    $this->get('/admin/dashboard')->assertRedirect('/admin/login');
});
