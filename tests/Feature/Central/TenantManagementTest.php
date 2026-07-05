<?php

use App\Actions\ProvisionTenant;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'password',
    ]);
});

it('lets a super-admin create a tenant with its first user from the admin panel', function () {
    $this->actingAs($this->admin, 'central')
        ->post('/admin/tenants', [
            'name' => 'Acme Co',
            'slug' => 'acme',
            'admin_name' => 'Ada Admin',
            'admin_email' => 'ada@acme.test',
            'admin_password' => 'secret-password',
        ])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('success');

    $tenant = Tenant::where('slug', 'acme')->first();
    expect($tenant)->not->toBeNull();

    // The first user was seeded inside the tenant's own database.
    $tenant->run(fn () => expect(User::where('email', 'ada@acme.test')->exists())->toBeTrue());
});

it('rejects a reserved slug', function () {
    $this->actingAs($this->admin, 'central')
        ->post('/admin/tenants', [
            'name' => 'Admin Corp', 'slug' => 'admin',
            'admin_name' => 'X', 'admin_email' => 'x@x.test', 'admin_password' => 'secret-password',
        ])
        ->assertSessionHasErrors('slug');

    expect(Tenant::where('slug', 'admin')->exists())->toBeFalse();
});

it('rejects a duplicate slug', function () {
    app(ProvisionTenant::class)->handle('Acme', 'acme', 'A', 'a@acme.test', 'secret-password');

    $this->actingAs($this->admin, 'central')
        ->post('/admin/tenants', [
            'name' => 'Acme Two', 'slug' => 'acme',
            'admin_name' => 'B', 'admin_email' => 'b@acme.test', 'admin_password' => 'secret-password',
        ])
        ->assertSessionHasErrors('slug');
});

it('rejects an invalid (non-kebab) slug', function () {
    $this->actingAs($this->admin, 'central')
        ->post('/admin/tenants', [
            'name' => 'Bad', 'slug' => 'Bad Slug!',
            'admin_name' => 'X', 'admin_email' => 'x@x.test', 'admin_password' => 'secret-password',
        ])
        ->assertSessionHasErrors('slug');
});

it('forbids guests from creating tenants', function () {
    $this->post('/admin/tenants', [
        'name' => 'Acme', 'slug' => 'acme',
        'admin_name' => 'X', 'admin_email' => 'x@x.test', 'admin_password' => 'secret-password',
    ])->assertRedirect(route('admin.login'));

    expect(Tenant::where('slug', 'acme')->exists())->toBeFalse();
});
