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

/** Create a role with the given permissions inside the acme tenant. */
function makeAcmeRole(string $name, array $permissions = []): void
{
    test()->tenant->run(function () use ($name, $permissions) {
        Role::create(['name' => $name, 'guard_name' => 'web'])
            ->syncPermissions($permissions);
    });
}

it('redirects a guest from the users page to the tenant login', function () {
    $this->get('/acme/users')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('lists users (deactivated included) with the assignable roles', function () {
    $this->tenant->run(function () {
        $user = User::create(['name' => 'Gone', 'email' => 'gone@acme.test', 'password' => 'password123']);
        $user->delete();
    });

    loginAsAcmeUser();

    $this->get('/acme/users')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/users/index')
            ->has('users.data', 2)
            ->where('users.data.0.is_active', true)
            ->has('roles')
            ->where('roles.0', 'Administrator')
        );
});

it('creates a user with a role', function () {
    makeAcmeRole('Staff', ['products.view']);

    loginAsAcmeUser();

    $this->from('/acme/users')
        ->post('/acme/users', [
            'name' => 'Sam',
            'email' => 'sam@acme.test',
            'role' => 'Staff',
            'password' => 'password123',
        ])
        ->assertRedirect('/acme/users')
        ->assertToast('User added — share their password so they can sign in.');

    $this->tenant->run(function () {
        $user = User::firstWhere('email', 'sam@acme.test');
        expect($user)->not->toBeNull()
            ->and($user->getRoleNames()->all())->toBe(['Staff']);
    });
});

it('requires a password when creating a user', function () {
    makeAcmeRole('Staff');

    loginAsAcmeUser();

    $this->from('/acme/users')
        ->post('/acme/users', [
            'name' => 'Sam',
            'email' => 'sam@acme.test',
            'role' => 'Staff',
        ])
        ->assertSessionHasErrors('password');
});

it('validates the email and requires a real role', function () {
    loginAsAcmeUser();

    $this->from('/acme/users')
        ->post('/acme/users', [
            'name' => 'Sam',
            'email' => 'not-an-email',
            'role' => 'Ghost role',
            'password' => 'password123',
        ])
        ->assertSessionHasErrors(['email', 'role']);
});

it('rejects a duplicate email', function () {
    makeAcmeRole('Staff');

    loginAsAcmeUser();

    $this->from('/acme/users')
        ->post('/acme/users', [
            'name' => 'Clone',
            'email' => 'ada@acme.test',
            'role' => 'Staff',
            'password' => 'password123',
        ])
        ->assertSessionHasErrors('email');
});

it('updates a user and changes their role', function () {
    makeAcmeRole('Staff', ['products.view']);
    $id = $this->tenant->run(function () {
        $user = User::create(['name' => 'Sam', 'email' => 'sam@acme.test', 'password' => 'password123']);
        $user->assignRole('Staff');

        return $user->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/users')
        ->put("/acme/users/{$id}", [
            'name' => 'Samantha',
            'email' => 'samantha@acme.test',
            'role' => 'Administrator',
        ])
        ->assertRedirect('/acme/users')
        ->assertToast('User saved.');

    $this->tenant->run(function () use ($id) {
        $user = User::find($id);
        expect($user->name)->toBe('Samantha')
            ->and($user->email)->toBe('samantha@acme.test')
            ->and($user->getRoleNames()->all())->toBe(['Administrator']);
    });
});

it('keeps the current password when the edit leaves it blank', function () {
    makeAcmeRole('Staff');
    $id = $this->tenant->run(function () {
        $user = User::create(['name' => 'Sam', 'email' => 'sam@acme.test', 'password' => 'password123']);
        $user->assignRole('Staff');

        return $user->id;
    });
    $before = $this->tenant->run(fn () => User::find($id)->password);

    loginAsAcmeUser();

    $this->from('/acme/users')
        ->put("/acme/users/{$id}", [
            'name' => 'Sam',
            'email' => 'sam@acme.test',
            'role' => 'Staff',
            'password' => '',
        ])
        ->assertRedirect('/acme/users');

    $after = $this->tenant->run(fn () => User::find($id)->password);
    expect($after)->toBe($before);
});

it('deactivates a user (soft delete)', function () {
    makeAcmeRole('Staff');
    $id = $this->tenant->run(function () {
        $user = User::create(['name' => 'Sam', 'email' => 'sam@acme.test', 'password' => 'password123']);
        $user->assignRole('Staff');

        return $user->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/users')
        ->delete("/acme/users/{$id}")
        ->assertRedirect('/acme/users')
        ->assertToast("User deactivated — they can't sign in until you reactivate them.");

    $this->tenant->run(function () use ($id) {
        expect(User::find($id))->toBeNull()
            ->and(User::withTrashed()->find($id))->not->toBeNull();
    });
});

it('reactivates a deactivated user', function () {
    makeAcmeRole('Staff');
    $id = $this->tenant->run(function () {
        $user = User::create(['name' => 'Sam', 'email' => 'sam@acme.test', 'password' => 'password123']);
        $user->assignRole('Staff');
        $user->delete();

        return $user->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/users')
        ->post("/acme/users/{$id}/restore")
        ->assertRedirect('/acme/users')
        ->assertToast('User reactivated.');

    $this->tenant->run(fn () => expect(User::find($id))->not->toBeNull());
});

it('stops you deactivating your own account', function () {
    $adaId = $this->tenant->run(fn () => User::firstWhere('email', 'ada@acme.test')->id);

    loginAsAcmeUser();

    $this->from('/acme/users')
        ->delete("/acme/users/{$adaId}")
        ->assertRedirect('/acme/users')
        ->assertToast("You can't deactivate your own account.", 'error');

    $this->tenant->run(fn () => expect(User::find($adaId))->not->toBeNull());
});

it('stops the last admin from dropping their own admin role', function () {
    // A role with full access to catalog but no user management.
    makeAcmeRole('Staff', ['products.view']);
    $adaId = $this->tenant->run(fn () => User::firstWhere('email', 'ada@acme.test')->id);

    loginAsAcmeUser();

    $this->from('/acme/users')
        ->put("/acme/users/{$adaId}", [
            'name' => 'Ada',
            'email' => 'ada@acme.test',
            'role' => 'Staff',
        ])
        ->assertRedirect('/acme/users')
        ->assertToast('There must always be someone who can manage users.', 'error');

    $this->tenant->run(function () use ($adaId) {
        expect(User::find($adaId)->getRoleNames()->all())->toBe(['Administrator']);
    });
});
