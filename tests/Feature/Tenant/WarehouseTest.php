<?php

use App\Actions\ProvisionTenant;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('redirects a guest from the warehouses page to the tenant login', function () {
    $this->get('/acme/warehouses')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('lists a tenant’s warehouses, paginated', function () {
    $this->tenant->run(function () {
        Warehouse::create(['name' => 'Main Warehouse', 'code' => 'WH-01']);
        Warehouse::create(['name' => 'Overflow']);
    });

    loginAsAcmeUser();

    $this->get('/acme/warehouses?per_page=10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/warehouses/index')
            ->has('warehouses.data', 2)
            ->where('warehouses.total', 2)
            ->where('filters.per_page', 10)
        );
});

it('creates a warehouse', function () {
    loginAsAcmeUser();

    $this->from('/acme/warehouses')
        ->post('/acme/warehouses', [
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'address' => '1 Foundry Rd',
        ])
        ->assertRedirect('/acme/warehouses')
        ->assertToast('Warehouse created.');

    $this->tenant->run(function () {
        expect(Warehouse::where('name', 'Main Warehouse')->exists())->toBeTrue();
    });
});

it('rejects a duplicate warehouse code', function () {
    $this->tenant->run(fn () => Warehouse::create(['name' => 'Main', 'code' => 'WH-01']));

    loginAsAcmeUser();

    $this->from('/acme/warehouses')
        ->post('/acme/warehouses', ['name' => 'Other', 'code' => 'WH-01'])
        ->assertRedirect('/acme/warehouses')
        ->assertSessionHasErrors('code');
});

it('allows multiple warehouses with no code', function () {
    loginAsAcmeUser();

    $this->post('/acme/warehouses', ['name' => 'No Code One'])->assertSessionHasNoErrors();
    $this->post('/acme/warehouses', ['name' => 'No Code Two'])->assertSessionHasNoErrors();

    $this->tenant->run(function () {
        expect(Warehouse::whereNull('code')->count())->toBe(2);
    });
});

it('updates a warehouse', function () {
    $id = $this->tenant->run(fn () => Warehouse::create(['name' => 'Main'])->id);

    loginAsAcmeUser();

    $this->from('/acme/warehouses')
        ->put("/acme/warehouses/{$id}", ['name' => 'Main Warehouse Ltd'])
        ->assertRedirect('/acme/warehouses')
        ->assertToast('Warehouse updated.');

    $this->tenant->run(function () use ($id) {
        expect(Warehouse::find($id)->name)->toBe('Main Warehouse Ltd');
    });
});

it('soft-deletes a warehouse', function () {
    $id = $this->tenant->run(fn () => Warehouse::create(['name' => 'Main'])->id);

    loginAsAcmeUser();

    $this->from('/acme/warehouses')
        ->delete("/acme/warehouses/{$id}")
        ->assertRedirect('/acme/warehouses')
        ->assertToast('Warehouse deleted.');

    $this->tenant->run(function () use ($id) {
        expect(Warehouse::find($id))->toBeNull()
            ->and(Warehouse::withTrashed()->find($id))->not->toBeNull();
    });
});

it('searches warehouses by name or code', function () {
    $this->tenant->run(function () {
        Warehouse::create(['name' => 'Main Warehouse', 'code' => 'WH-01']);
        Warehouse::create(['name' => 'Overflow Depot', 'code' => 'WH-02']);
    });

    loginAsAcmeUser();

    $this->get('/acme/warehouses?search=WH-02')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('warehouses.data', 1)
            ->where('warehouses.data.0.name', 'Overflow Depot')
        );
});
