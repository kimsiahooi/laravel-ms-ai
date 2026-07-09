<?php

use App\Actions\ProvisionTenant;
use App\Models\RawMaterial;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('redirects a guest from the raw materials page to the tenant login', function () {
    $this->get('/acme/raw-materials')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('lists a tenant’s raw materials, paginated', function () {
    $this->tenant->run(function () {
        RawMaterial::create(['name' => 'Steel Rod', 'sku' => 'RM-001', 'unit' => 'kg']);
        RawMaterial::create(['name' => 'Copper Wire', 'sku' => 'RM-002', 'unit' => 'm']);
    });

    loginAsAcmeUser();

    $this->get('/acme/raw-materials?per_page=10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/raw-materials/index')
            ->has('rawMaterials.data', 2)
            ->where('rawMaterials.total', 2)
            ->where('filters.per_page', 10)
        );
});

it('creates a raw material and defaults min_stock to 0', function () {
    loginAsAcmeUser();

    $this->from('/acme/raw-materials')
        ->post('/acme/raw-materials', [
            'name' => 'Steel Rod',
            'sku' => 'RM-001',
            'unit' => 'kg',
        ])
        ->assertRedirect('/acme/raw-materials')
        ->assertSessionHas('success');

    $this->tenant->run(function () {
        $rm = RawMaterial::firstWhere('sku', 'RM-001');
        expect($rm)->not->toBeNull()
            ->and((float) $rm->min_stock)->toBe(0.0);
    });
});

it('rejects a duplicate sku', function () {
    $this->tenant->run(fn () => RawMaterial::create(['name' => 'Steel', 'sku' => 'RM-001', 'unit' => 'kg']));

    loginAsAcmeUser();

    $this->from('/acme/raw-materials')
        ->post('/acme/raw-materials', ['name' => 'Other', 'sku' => 'RM-001', 'unit' => 'kg'])
        ->assertRedirect('/acme/raw-materials')
        ->assertSessionHasErrors('sku');
});

it('updates a raw material', function () {
    $id = $this->tenant->run(
        fn () => RawMaterial::create(['name' => 'Steel', 'sku' => 'RM-001', 'unit' => 'kg'])->id,
    );

    loginAsAcmeUser();

    $this->from('/acme/raw-materials')
        ->put("/acme/raw-materials/{$id}", [
            'name' => 'Steel Rod', 'sku' => 'RM-001', 'unit' => 'kg', 'min_stock' => 25.5,
        ])
        ->assertRedirect('/acme/raw-materials')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect((float) RawMaterial::find($id)->min_stock)->toBe(25.5);
    });
});

it('soft-deletes a raw material', function () {
    $id = $this->tenant->run(
        fn () => RawMaterial::create(['name' => 'Steel', 'sku' => 'RM-001', 'unit' => 'kg'])->id,
    );

    loginAsAcmeUser();

    $this->from('/acme/raw-materials')
        ->delete("/acme/raw-materials/{$id}")
        ->assertRedirect('/acme/raw-materials')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(RawMaterial::find($id))->toBeNull()
            ->and(RawMaterial::withTrashed()->find($id))->not->toBeNull();
    });
});

it('searches raw materials by name or sku', function () {
    $this->tenant->run(function () {
        RawMaterial::create(['name' => 'Steel Rod', 'sku' => 'RM-001', 'unit' => 'kg']);
        RawMaterial::create(['name' => 'Copper Wire', 'sku' => 'RM-002', 'unit' => 'm']);
    });

    loginAsAcmeUser();

    $this->get('/acme/raw-materials?search=RM-002')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rawMaterials.data', 1)
            ->where('rawMaterials.data.0.sku', 'RM-002')
        );
});
