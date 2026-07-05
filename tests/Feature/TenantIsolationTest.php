<?php

use App\Actions\ProvisionTenant;
use App\Models\User;

// End-to-end two-tenant isolation smoke (Phase 1 capstone). Covers the isolation
// guarantees that are faithful to test here: separate databases (each with only
// its own user) and login-auth isolation across tenants. Cross-tenant *session*
// isolation is provided in production by the per-tenant `sessions` table (the DB
// session driver follows the switched connection, so a session id created in one
// tenant's DB is simply absent from another's) — it can't be reproduced with the
// suite's array session driver + shared test-client session, so it isn't asserted
// here.
it('keeps two tenants isolated in data and login auth', function () {
    $acme = app(ProvisionTenant::class)->handle(
        'Acme',
        'acme',
        'Ada',
        'a@acme.test',
        'password123',
    );
    $globex = app(ProvisionTenant::class)->handle(
        'Globex',
        'globex',
        'Greg',
        'g@globex.test',
        'password123',
    );

    // Data isolation — each tenant database holds exactly its own single user.
    expect($acme->run(fn () => User::pluck('email')->all()))
        ->toBe(['a@acme.test']);
    expect($globex->run(fn () => User::pluck('email')->all()))
        ->toBe(['g@globex.test']);

    // Login auth isolation — Acme's user cannot authenticate against Globex
    // (Ada exists only in Acme's database).
    $this->from('/globex/login')
        ->post('/globex/login', [
            'email' => 'a@acme.test',
            'password' => 'password123',
        ])
        ->assertRedirect('/globex/login')
        ->assertSessionHasErrors('email');

    // Positive path — a tenant's own user logs in and reaches that tenant's
    // dashboard.
    $this->post('/acme/login', [
        'email' => 'a@acme.test',
        'password' => 'password123',
    ])->assertRedirect(route('tenant.dashboard', ['tenant' => 'acme']));

    $this->get('/acme/dashboard')->assertOk();
});
