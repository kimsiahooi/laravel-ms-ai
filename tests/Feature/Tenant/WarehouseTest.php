<?php

use App\Actions\ProvisionTenant;
use App\Models\Location;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/** Create a location (site) in the Acme tenant and return its id. */
function acmeLocationId(string $name = 'KL HQ'): int
{
    return test()->tenant->run(fn () => Location::create(['name' => $name])->id);
}

it('redirects a guest from the warehouses page to the tenant login', function () {
    $this->get('/acme/warehouses')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('belongs to a location, and a location exposes its warehouses', function () {
    $this->tenant->run(function () {
        $location = Location::create(['name' => 'KL HQ']);
        $warehouse = Warehouse::create(['location_id' => $location->id, 'name' => 'Main Store']);

        expect($warehouse->location->name)->toBe('KL HQ')
            ->and($location->warehouses()->count())->toBe(1);
    });
});

it('lists a tenant’s warehouses, paginated', function () {
    $this->tenant->run(function () {
        $loc = Location::create(['name' => 'KL HQ']);
        Warehouse::create(['location_id' => $loc->id, 'name' => 'Main Warehouse', 'code' => 'WH-01']);
        Warehouse::create(['location_id' => $loc->id, 'name' => 'Overflow']);
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

it('creates a warehouse under a location', function () {
    $locId = acmeLocationId();

    loginAsAcmeUser();

    $this->from('/acme/warehouses')
        ->post('/acme/warehouses', [
            'location_id' => $locId,
            'name' => 'Main Warehouse',
            'code' => 'WH-01',
            'address' => '1 Foundry Rd',
        ])
        ->assertRedirect('/acme/warehouses')
        ->assertToast('Warehouse created.');

    $this->tenant->run(function () use ($locId) {
        $wh = Warehouse::where('name', 'Main Warehouse')->first();
        expect($wh)->not->toBeNull()
            ->and($wh->location_id)->toBe($locId);
    });
});

it('requires a location', function () {
    loginAsAcmeUser();

    $this->from('/acme/warehouses')
        ->post('/acme/warehouses', ['name' => 'Orphan'])
        ->assertRedirect('/acme/warehouses')
        ->assertSessionHasErrors('location_id');
});

it('rejects a duplicate warehouse code', function () {
    $locId = acmeLocationId();
    $this->tenant->run(fn () => Warehouse::create(['location_id' => $locId, 'name' => 'Main', 'code' => 'WH-01']));

    loginAsAcmeUser();

    $this->from('/acme/warehouses')
        ->post('/acme/warehouses', ['location_id' => $locId, 'name' => 'Other', 'code' => 'WH-01'])
        ->assertRedirect('/acme/warehouses')
        ->assertSessionHasErrors('code');
});

it('allows multiple warehouses with no code', function () {
    $locId = acmeLocationId();

    loginAsAcmeUser();

    $this->post('/acme/warehouses', ['location_id' => $locId, 'name' => 'No Code One'])->assertSessionHasNoErrors();
    $this->post('/acme/warehouses', ['location_id' => $locId, 'name' => 'No Code Two'])->assertSessionHasNoErrors();

    $this->tenant->run(function () {
        expect(Warehouse::whereNull('code')->count())->toBe(2);
    });
});

it('updates a warehouse', function () {
    $locId = acmeLocationId();
    $id = $this->tenant->run(fn () => Warehouse::create(['location_id' => $locId, 'name' => 'Main'])->id);

    loginAsAcmeUser();

    $this->from('/acme/warehouses')
        ->put("/acme/warehouses/{$id}", ['location_id' => $locId, 'name' => 'Main Warehouse Ltd'])
        ->assertRedirect('/acme/warehouses')
        ->assertToast('Warehouse updated.');

    $this->tenant->run(function () use ($id) {
        expect(Warehouse::find($id)->name)->toBe('Main Warehouse Ltd');
    });
});

it('soft-deletes an empty warehouse', function () {
    $locId = acmeLocationId();
    $id = $this->tenant->run(fn () => Warehouse::create(['location_id' => $locId, 'name' => 'Main'])->id);

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

it('blocks deleting a warehouse that holds stock, then allows it once zeroed', function () {
    $locId = acmeLocationId();

    [$id, $stockId] = $this->tenant->run(function () use ($locId) {
        $warehouse = Warehouse::create(['location_id' => $locId, 'name' => 'Main']);
        $stock = WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'stockable_type' => 'product',
            'stockable_id' => 1,
            'quantity' => 5,
        ]);

        return [$warehouse->id, $stock->id];
    });

    loginAsAcmeUser();

    // On-hand stock blocks the delete: an error toast, and the row survives.
    $this->from('/acme/warehouses')
        ->delete("/acme/warehouses/{$id}")
        ->assertRedirect('/acme/warehouses')
        ->assertToast('Move or adjust this warehouse’s stock to zero before deleting it.', 'error');

    $this->tenant->run(fn () => expect(Warehouse::find($id))->not->toBeNull());

    // Zero the on-hand, and the delete goes through.
    $this->tenant->run(fn () => WarehouseStock::where('id', $stockId)->update(['quantity' => 0]));

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
        $loc = Location::create(['name' => 'KL HQ']);
        Warehouse::create(['location_id' => $loc->id, 'name' => 'Main Warehouse', 'code' => 'WH-01']);
        Warehouse::create(['location_id' => $loc->id, 'name' => 'Overflow Depot', 'code' => 'WH-02']);
    });

    loginAsAcmeUser();

    $this->get('/acme/warehouses?search=WH-02')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('warehouses.data', 1)
            ->where('warehouses.data.0.name', 'Overflow Depot')
        );
});
