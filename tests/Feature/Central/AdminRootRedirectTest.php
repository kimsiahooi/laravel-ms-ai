<?php

use App\Models\CentralUser;

it('redirects a guest from /admin to the admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('redirects an authenticated admin from /admin to the dashboard', function () {
    $admin = CentralUser::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin, 'central')
        ->get('/admin')
        ->assertRedirect('/admin/dashboard');
});
