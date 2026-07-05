<?php

use App\Models\CentralUser;

it('logs a central super-admin into the admin dashboard', function () {
    $admin = CentralUser::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'password',
    ]);

    $this->post('/admin/login', ['email' => 'root@example.com', 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin, 'central');
});

it('sends a super-admin to the admin dashboard even with a stale intended URL', function () {
    CentralUser::create(['name' => 'Root', 'email' => 'root@example.com', 'password' => 'password']);

    // A leftover intended URL (e.g. from the starter /dashboard) must not misroute.
    $this->withSession(['url.intended' => 'http://laravel-ms-ai.test/dashboard'])
        ->post('/admin/login', ['email' => 'root@example.com', 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));
});

it('rejects bad central credentials', function () {
    CentralUser::create(['name' => 'Root', 'email' => 'root@example.com', 'password' => 'password']);

    $this->from('/admin/login')
        ->post('/admin/login', ['email' => 'root@example.com', 'password' => 'wrong'])
        ->assertRedirect('/admin/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest('central');
});

it('redirects guests from the admin dashboard to the admin login', function () {
    $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    $this->assertGuest('central');
});

it('does not authenticate a central super-admin on the tenant web guard', function () {
    CentralUser::create(['name' => 'Root', 'email' => 'root@example.com', 'password' => 'password']);

    $this->post('/admin/login', ['email' => 'root@example.com', 'password' => 'password']);

    $this->assertGuest('web');
});

it('logs a central super-admin out', function () {
    CentralUser::create(['name' => 'Root', 'email' => 'root@example.com', 'password' => 'password']);

    $this->post('/admin/login', ['email' => 'root@example.com', 'password' => 'password']);
    $this->assertAuthenticated('central');

    $this->post('/admin/logout')->assertRedirect(route('admin.login'));
    $this->assertGuest('central');
});
