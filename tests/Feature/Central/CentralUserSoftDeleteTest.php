<?php

use App\Models\CentralUser;

it('soft deletes a central user (row retained, marked deleted)', function () {
    $user = CentralUser::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'password',
    ]);

    $user->delete();

    expect(CentralUser::find($user->id))->toBeNull()
        ->and(CentralUser::withTrashed()->find($user->id))->not->toBeNull()
        ->and(CentralUser::withTrashed()->find($user->id)->trashed())->toBeTrue();
});

it('blocks a soft-deleted central admin from logging in', function () {
    $user = CentralUser::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'password',
    ]);

    $user->delete();

    $this->post('/admin/login', [
        'email' => 'root@example.com',
        'password' => 'password',
    ]);

    $this->assertGuest('central');
});

it('lets a restored central admin log in again', function () {
    $user = CentralUser::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'password',
    ]);

    $user->delete();
    CentralUser::withTrashed()->find($user->id)->restore();

    $this->post('/admin/login', [
        'email' => 'root@example.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user->fresh(), 'central');
});
