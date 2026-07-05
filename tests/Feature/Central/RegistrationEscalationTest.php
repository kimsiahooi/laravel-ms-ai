<?php

use App\Models\CentralUser;

it('SECURITY: a self-registered user must NOT become a central super-admin', function () {
    // Simulate the retained Fortify /register at the central root.
    $this->post('/register', [
        'name' => 'Attacker',
        'email' => 'attacker@evil.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    // Those credentials must NOT authenticate against the central super-admin guard.
    $this->post('/admin/login', [
        'email' => 'attacker@evil.test',
        'password' => 'password',
    ]);

    expect(CentralUser::where('email', 'attacker@evil.test')->exists())->toBeFalse();
    $this->assertGuest('central');
});
