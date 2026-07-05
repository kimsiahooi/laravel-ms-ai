<?php

use App\Actions\ProvisionTenant;
use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

function tenantDbExists(string $slug): bool
{
    $name = config('tenancy.database.prefix').$slug;

    // A bound placeholder inside `SHOW DATABASES LIKE ?` is rejected under this
    // project's PDO config (native prepared statements, EMULATE_PREPARES off),
    // so query the catalog view instead — same semantics.
    return DB::connection('central')->select(
        'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
        [$name],
    ) !== [];
}

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'password',
    ]);

    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('soft deletes a tenant and keeps its database', function () {
    $this->actingAs($this->admin, 'central')
        ->from('/admin/tenants')
        ->delete('/admin/tenants/acme')
        ->assertRedirect('/admin/tenants')
        ->assertSessionHas('success');

    expect(Tenant::withTrashed()->find('acme')->trashed())->toBeTrue()
        ->and(tenantDbExists('acme'))->toBeTrue();
});

it('makes a soft-deleted tenant inaccessible', function () {
    $this->tenant->delete();

    $this->get('/acme/login')->assertNotFound();
});

it('lists only soft-deleted tenants on the archived page', function () {
    $this->tenant->delete();

    $this->actingAs($this->admin, 'central')
        ->get('/admin/tenants/trashed')
        ->assertInertia(fn ($page) => $page
            ->component('admin/tenants/trashed')
            ->has('tenants.data', 1)
            ->where('tenants.data.0.slug', 'acme')
        );
});

it('restores a soft-deleted tenant and makes it reachable again', function () {
    $this->tenant->delete();

    $this->actingAs($this->admin, 'central')
        ->from('/admin/tenants/trashed')
        ->patch('/admin/tenants/acme/restore')
        ->assertRedirect('/admin/tenants/trashed')
        ->assertSessionHas('success');

    expect(Tenant::find('acme'))->not->toBeNull();

    $this->get('/acme/login')->assertOk();
});

it('force deletes a trashed tenant and drops its database', function () {
    $this->tenant->delete();

    $this->actingAs($this->admin, 'central')
        ->from('/admin/tenants/trashed')
        ->delete('/admin/tenants/acme/force')
        ->assertRedirect('/admin/tenants/trashed')
        ->assertSessionHas('success');

    expect(Tenant::withTrashed()->find('acme'))->toBeNull()
        ->and(tenantDbExists('acme'))->toBeFalse();
});

it('refuses to force delete a tenant that was never archived', function () {
    $this->actingAs($this->admin, 'central')
        ->delete('/admin/tenants/acme/force')
        ->assertNotFound();

    expect(Tenant::find('acme'))->not->toBeNull()
        ->and(tenantDbExists('acme'))->toBeTrue();
});

it('refuses to restore a tenant that is not archived', function () {
    $this->actingAs($this->admin, 'central')
        ->patch('/admin/tenants/acme/restore')
        ->assertNotFound();
});

it('rejects lifecycle routes for guests', function () {
    $this->delete('/admin/tenants/acme')->assertRedirect('/admin/login');
    $this->get('/admin/tenants/trashed')->assertRedirect('/admin/login');
    $this->patch('/admin/tenants/acme/restore')->assertRedirect('/admin/login');
    $this->delete('/admin/tenants/acme/force')->assertRedirect('/admin/login');
});
