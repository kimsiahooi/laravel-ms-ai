<?php

use App\Actions\ProvisionTenant;
use App\Models\Supplier;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('redirects a guest from the suppliers page to the tenant login', function () {
    $this->get('/acme/suppliers')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('lists a tenant’s suppliers, paginated', function () {
    $this->tenant->run(function () {
        Supplier::create(['name' => 'Acme Metals', 'email' => 'metals@acme.test']);
        Supplier::create(['name' => 'Bolt Co']);
    });

    loginAsAcmeUser();

    $this->get('/acme/suppliers?per_page=10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/suppliers/index')
            ->has('suppliers.data', 2)
            ->where('suppliers.total', 2)
            ->where('filters.per_page', 10)
        );
});

it('creates a supplier', function () {
    loginAsAcmeUser();

    $this->from('/acme/suppliers')
        ->post('/acme/suppliers', [
            'name' => 'Acme Metals',
            'email' => 'metals@acme.test',
            'phone' => '+60 12-345 6789',
            'address' => '1 Foundry Rd',
            'notes' => 'Primary steel supplier',
        ])
        ->assertRedirect('/acme/suppliers')
        ->assertSessionHas('success');

    $this->tenant->run(function () {
        expect(Supplier::where('name', 'Acme Metals')->exists())->toBeTrue();
    });
});

it('rejects a duplicate supplier email', function () {
    $this->tenant->run(fn () => Supplier::create(['name' => 'Metals', 'email' => 'dup@acme.test']));

    loginAsAcmeUser();

    $this->from('/acme/suppliers')
        ->post('/acme/suppliers', ['name' => 'Other', 'email' => 'dup@acme.test'])
        ->assertRedirect('/acme/suppliers')
        ->assertSessionHasErrors('email');
});

it('allows multiple suppliers with no email', function () {
    loginAsAcmeUser();

    $this->post('/acme/suppliers', ['name' => 'No Email One'])->assertSessionHasNoErrors();
    $this->post('/acme/suppliers', ['name' => 'No Email Two'])->assertSessionHasNoErrors();

    $this->tenant->run(function () {
        expect(Supplier::whereNull('email')->count())->toBe(2);
    });
});

it('updates a supplier', function () {
    $id = $this->tenant->run(fn () => Supplier::create(['name' => 'Metals'])->id);

    loginAsAcmeUser();

    $this->from('/acme/suppliers')
        ->put("/acme/suppliers/{$id}", ['name' => 'Acme Metals Ltd'])
        ->assertRedirect('/acme/suppliers')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(Supplier::find($id)->name)->toBe('Acme Metals Ltd');
    });
});

it('soft-deletes a supplier', function () {
    $id = $this->tenant->run(fn () => Supplier::create(['name' => 'Metals'])->id);

    loginAsAcmeUser();

    $this->from('/acme/suppliers')
        ->delete("/acme/suppliers/{$id}")
        ->assertRedirect('/acme/suppliers')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(Supplier::find($id))->toBeNull()
            ->and(Supplier::withTrashed()->find($id))->not->toBeNull();
    });
});
