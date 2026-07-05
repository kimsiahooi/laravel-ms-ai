<?php

use App\Actions\ProvisionTenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme',
        'acme',
        'Ada',
        'ada@acme.test',
        'password123',
    );
});

it('soft deletes a tenant user (row retained, marked deleted)', function () {
    $this->tenant->run(function () {
        $user = User::where('email', 'ada@acme.test')->firstOrFail();

        $user->delete();

        expect(User::where('email', 'ada@acme.test')->exists())->toBeFalse()
            ->and(User::withTrashed()->where('email', 'ada@acme.test')->exists())->toBeTrue()
            ->and(User::withTrashed()->firstWhere('email', 'ada@acme.test')->trashed())->toBeTrue();
    });
});

it('blocks a soft-deleted tenant user from logging in', function () {
    $this->tenant->run(function () {
        User::where('email', 'ada@acme.test')->firstOrFail()->delete();
    });

    $this->post('/acme/login', [
        'email' => 'ada@acme.test',
        'password' => 'password123',
    ]);

    $this->assertGuest('web');
});

it('lets a restored tenant user log in again', function () {
    $this->tenant->run(function () {
        $user = User::where('email', 'ada@acme.test')->firstOrFail();
        $user->delete();
        User::withTrashed()->firstWhere('email', 'ada@acme.test')->restore();
    });

    $this->post('/acme/login', [
        'email' => 'ada@acme.test',
        'password' => 'password123',
    ]);

    $this->assertAuthenticated('web');
});
