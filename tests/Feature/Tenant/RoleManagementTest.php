<?php

use App\Actions\ProvisionTenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('redirects a guest from the roles page to the tenant login', function () {
    $this->get('/acme/roles')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('lists roles with the permission catalog', function () {
    loginAsAcmeUser();

    $this->get('/acme/roles')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/roles/index')
            ->has('roles')
            ->where('roles.0.name', 'Administrator')
            ->where('roles.0.is_locked', true)
            ->has('permissionGroups')
            ->has('permissionGroups.0.permissions')
        );
});

it('creates a role with the selected permissions', function () {
    loginAsAcmeUser();

    $this->from('/acme/roles')
        ->post('/acme/roles', [
            'name' => 'Warehouse staff',
            'permissions' => ['products.view', 'suppliers.view'],
        ])
        ->assertRedirect('/acme/roles')
        ->assertToast('Role saved.');

    $this->tenant->run(function () {
        $role = Role::where('name', 'Warehouse staff')->first();
        expect($role)->not->toBeNull()
            ->and($role->permissions->pluck('name')->all())
            ->toEqualCanonicalizing(['products.view', 'suppliers.view']);
    });
});

it('updates a role’s name and permissions', function () {
    $id = $this->tenant->run(function () {
        $role = Role::create(['name' => 'Staff', 'guard_name' => 'web']);
        $role->syncPermissions(['products.view']);

        return $role->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/roles')
        ->put("/acme/roles/{$id}", [
            'name' => 'Floor staff',
            'permissions' => ['products.view', 'products.create'],
        ])
        ->assertRedirect('/acme/roles')
        ->assertToast('Role saved.');

    $this->tenant->run(function () use ($id) {
        $role = Role::find($id);
        expect($role->name)->toBe('Floor staff')
            ->and($role->permissions->pluck('name')->all())
            ->toEqualCanonicalizing(['products.view', 'products.create']);
    });
});

it('blocks editing the built-in Administrator role', function () {
    $id = $this->tenant->run(fn () => Role::where('name', 'Administrator')->first()->id);

    loginAsAcmeUser();

    $this->from('/acme/roles')
        ->put("/acme/roles/{$id}", ['name' => 'Hacked', 'permissions' => []])
        ->assertForbidden();

    $this->tenant->run(function () use ($id) {
        expect(Role::find($id)->name)->toBe('Administrator');
    });
});

it('blocks deleting the built-in Administrator role', function () {
    $id = $this->tenant->run(fn () => Role::where('name', 'Administrator')->first()->id);

    loginAsAcmeUser();

    $this->from('/acme/roles')
        ->delete("/acme/roles/{$id}")
        ->assertRedirect('/acme/roles')
        ->assertToast("The Administrator role can't be deleted.", 'error');

    $this->tenant->run(fn () => expect(Role::where('name', 'Administrator')->exists())->toBeTrue());
});

it('blocks deleting a role that still has people', function () {
    $id = $this->tenant->run(function () {
        $role = Role::create(['name' => 'Staff', 'guard_name' => 'web']);
        User::create(['name' => 'Sam', 'email' => 'sam@acme.test', 'password' => 'password123'])
            ->assignRole($role);

        return $role->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/roles')
        ->delete("/acme/roles/{$id}")
        ->assertRedirect('/acme/roles')
        ->assertToast("Reassign this role's people before deleting it.", 'error');

    $this->tenant->run(fn () => expect(Role::find($id))->not->toBeNull());
});

it('deletes an unused role', function () {
    $id = $this->tenant->run(fn () => Role::create(['name' => 'Temp', 'guard_name' => 'web'])->id);

    loginAsAcmeUser();

    $this->from('/acme/roles')
        ->delete("/acme/roles/{$id}")
        ->assertRedirect('/acme/roles')
        ->assertToast('Role deleted.');

    $this->tenant->run(fn () => expect(Role::find($id))->toBeNull());
});

it('requires a role name and rejects duplicates', function () {
    $this->tenant->run(fn () => Role::create(['name' => 'Sales', 'guard_name' => 'web']));

    loginAsAcmeUser();

    $this->from('/acme/roles')
        ->post('/acme/roles', ['name' => '', 'permissions' => []])
        ->assertSessionHasErrors('name');

    $this->from('/acme/roles')
        ->post('/acme/roles', ['name' => 'Sales', 'permissions' => []])
        ->assertSessionHasErrors('name');
});

it('rejects an unknown permission name', function () {
    loginAsAcmeUser();

    $this->from('/acme/roles')
        ->post('/acme/roles', ['name' => 'Odd', 'permissions' => ['bogus.permission']])
        ->assertSessionHasErrors('permissions.0');
});
