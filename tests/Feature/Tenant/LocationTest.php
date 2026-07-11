<?php

use App\Actions\ProvisionTenant;
use App\Models\Location;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('redirects a guest from the locations page to the tenant login', function () {
    $this->get('/acme/locations')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('lists a tenant’s locations, paginated', function () {
    $this->tenant->run(function () {
        Location::create(['name' => 'KL HQ', 'code' => 'KL', 'address' => '1 Jalan Ampang']);
        Location::create(['name' => 'Penang Branch']);
    });

    loginAsAcmeUser();

    $this->get('/acme/locations?per_page=10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/locations/index')
            ->has('locations.data', 2)
            ->where('locations.total', 2)
            ->where('filters.per_page', 10)
        );
});

it('creates a location', function () {
    loginAsAcmeUser();

    $this->from('/acme/locations')
        ->post('/acme/locations', [
            'name' => 'KL HQ',
            'code' => 'KL',
            'address' => '1 Jalan Ampang',
        ])
        ->assertRedirect('/acme/locations')
        ->assertToast('Location created.');

    $this->tenant->run(function () {
        expect(Location::where('name', 'KL HQ')->where('code', 'KL')->exists())->toBeTrue();
    });
});

it('requires a name', function () {
    loginAsAcmeUser();

    $this->from('/acme/locations')
        ->post('/acme/locations', [])
        ->assertRedirect('/acme/locations')
        ->assertSessionHasErrors('name');
});

it('rejects a duplicate code', function () {
    $this->tenant->run(fn () => Location::create(['name' => 'KL HQ', 'code' => 'KL']));

    loginAsAcmeUser();

    $this->from('/acme/locations')
        ->post('/acme/locations', ['name' => 'Other', 'code' => 'KL'])
        ->assertRedirect('/acme/locations')
        ->assertSessionHasErrors('code');
});

it('allows multiple locations with no code', function () {
    loginAsAcmeUser();

    $this->post('/acme/locations', ['name' => 'No Code One'])->assertSessionHasNoErrors();
    $this->post('/acme/locations', ['name' => 'No Code Two'])->assertSessionHasNoErrors();

    $this->tenant->run(function () {
        expect(Location::whereNull('code')->count())->toBe(2);
    });
});

it('updates a location', function () {
    $id = $this->tenant->run(fn () => Location::create(['name' => 'KL HQ'])->id);

    loginAsAcmeUser();

    $this->from('/acme/locations')
        ->put("/acme/locations/{$id}", ['name' => 'KL Headquarters'])
        ->assertRedirect('/acme/locations')
        ->assertToast('Location updated.');

    $this->tenant->run(function () use ($id) {
        expect(Location::find($id)->name)->toBe('KL Headquarters');
    });
});

it('soft-deletes an empty location', function () {
    $id = $this->tenant->run(fn () => Location::create(['name' => 'KL HQ'])->id);

    loginAsAcmeUser();

    $this->from('/acme/locations')
        ->delete("/acme/locations/{$id}")
        ->assertRedirect('/acme/locations')
        ->assertToast('Location deleted.');

    $this->tenant->run(function () use ($id) {
        expect(Location::find($id))->toBeNull()
            ->and(Location::withTrashed()->find($id))->not->toBeNull();
    });
});

it('blocks deleting a location that still owns warehouses', function () {
    $id = $this->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);
        Warehouse::create(['location_id' => $location->id, 'name' => 'Main']);

        return $location->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/locations')
        ->delete("/acme/locations/{$id}")
        ->assertRedirect('/acme/locations')
        ->assertToast('Remove this location’s warehouses before deleting it.', 'error');

    // The location is still there — the block left it untouched.
    $this->tenant->run(function () use ($id) {
        expect(Location::find($id))->not->toBeNull();
    });
});

it('searches locations by name or code', function () {
    $this->tenant->run(function () {
        Location::create(['name' => 'KL HQ', 'code' => 'KL']);
        Location::create(['name' => 'Penang Branch', 'code' => 'PG']);
    });

    loginAsAcmeUser();

    $this->get('/acme/locations?search=PG')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('locations.data', 1)
            ->where('locations.data.0.name', 'Penang Branch')
        );
});
