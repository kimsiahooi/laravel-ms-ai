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

it('lists a tenant’s locations with warehouse options, paginated', function () {
    $this->tenant->run(function () {
        $warehouse = Warehouse::create(['name' => 'Main']);
        Location::create(['warehouse_id' => $warehouse->id, 'code' => 'A-01', 'name' => 'Aisle 1']);
        Location::create(['warehouse_id' => $warehouse->id, 'code' => 'A-02']);
    });

    loginAsAcmeUser();

    $this->get('/acme/locations?per_page=10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/locations/index')
            ->has('locations.data', 2)
            ->where('locations.total', 2)
            ->where('filters.per_page', 10)
            ->has('warehouses', 1)
        );
});

it('creates a location', function () {
    $warehouseId = $this->tenant->run(fn () => Warehouse::create(['name' => 'Main'])->id);

    loginAsAcmeUser();

    $this->from('/acme/locations')
        ->post('/acme/locations', [
            'warehouse_id' => $warehouseId,
            'code' => 'A-01',
            'name' => 'Aisle 1',
        ])
        ->assertRedirect('/acme/locations')
        ->assertToast('Location created.');

    $this->tenant->run(function () use ($warehouseId) {
        expect(Location::where('warehouse_id', $warehouseId)->where('code', 'A-01')->exists())->toBeTrue();
    });
});

it('requires a warehouse and code', function () {
    loginAsAcmeUser();

    $this->from('/acme/locations')
        ->post('/acme/locations', [])
        ->assertRedirect('/acme/locations')
        ->assertSessionHasErrors(['warehouse_id', 'code']);
});

it('rejects a duplicate code within the same warehouse', function () {
    $warehouseId = $this->tenant->run(function () {
        $warehouse = Warehouse::create(['name' => 'Main']);
        Location::create(['warehouse_id' => $warehouse->id, 'code' => 'A-01']);

        return $warehouse->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/locations')
        ->post('/acme/locations', ['warehouse_id' => $warehouseId, 'code' => 'A-01'])
        ->assertRedirect('/acme/locations')
        ->assertSessionHasErrors('code');
});

it('allows the same code in a different warehouse', function () {
    [$mainId, $overflowId] = $this->tenant->run(function () {
        $main = Warehouse::create(['name' => 'Main']);
        $overflow = Warehouse::create(['name' => 'Overflow']);
        Location::create(['warehouse_id' => $main->id, 'code' => 'A-01']);

        return [$main->id, $overflow->id];
    });

    loginAsAcmeUser();

    $this->from('/acme/locations')
        ->post('/acme/locations', ['warehouse_id' => $overflowId, 'code' => 'A-01'])
        ->assertRedirect('/acme/locations')
        ->assertSessionHasNoErrors();

    $this->tenant->run(function () use ($overflowId) {
        expect(Location::where('warehouse_id', $overflowId)->where('code', 'A-01')->exists())->toBeTrue();
    });
});

it('updates a location', function () {
    [$id, $warehouseId] = $this->tenant->run(function () {
        $warehouse = Warehouse::create(['name' => 'Main']);
        $location = Location::create(['warehouse_id' => $warehouse->id, 'code' => 'A-01']);

        return [$location->id, $warehouse->id];
    });

    loginAsAcmeUser();

    $this->from('/acme/locations')
        ->put("/acme/locations/{$id}", [
            'warehouse_id' => $warehouseId,
            'code' => 'A-01',
            'name' => 'Aisle One',
        ])
        ->assertRedirect('/acme/locations')
        ->assertToast('Location updated.');

    $this->tenant->run(function () use ($id) {
        expect(Location::find($id)->name)->toBe('Aisle One');
    });
});

it('soft-deletes a location', function () {
    $id = $this->tenant->run(function () {
        $warehouse = Warehouse::create(['name' => 'Main']);

        return Location::create(['warehouse_id' => $warehouse->id, 'code' => 'A-01'])->id;
    });

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

it('searches locations by code or name', function () {
    $this->tenant->run(function () {
        $warehouse = Warehouse::create(['name' => 'Main']);
        Location::create(['warehouse_id' => $warehouse->id, 'code' => 'A-01', 'name' => 'Aisle 1']);
        Location::create(['warehouse_id' => $warehouse->id, 'code' => 'B-02', 'name' => 'Bay 2']);
    });

    loginAsAcmeUser();

    $this->get('/acme/locations?search=B-02')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('locations.data', 1)
            ->where('locations.data.0.name', 'Bay 2')
        );
});
