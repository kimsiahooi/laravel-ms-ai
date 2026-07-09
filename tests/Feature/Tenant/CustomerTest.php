<?php

use App\Actions\ProvisionTenant;
use App\Models\Customer;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('redirects a guest from the customers page to the tenant login', function () {
    $this->get('/acme/customers')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('lists a tenant’s customers, paginated', function () {
    $this->tenant->run(function () {
        Customer::create(['name' => 'Globex', 'email' => 'buyer@globex.test']);
        Customer::create(['name' => 'Initech']);
    });

    loginAsAcmeUser();

    $this->get('/acme/customers?per_page=10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/customers/index')
            ->has('customers.data', 2)
            ->where('customers.total', 2)
            ->where('filters.per_page', 10)
        );
});

it('creates a customer', function () {
    loginAsAcmeUser();

    $this->from('/acme/customers')
        ->post('/acme/customers', [
            'name' => 'Globex',
            'email' => 'buyer@globex.test',
            'phone' => '+60 12-000 0000',
            'address' => '5 Market St',
            'notes' => 'Wholesale account',
        ])
        ->assertRedirect('/acme/customers')
        ->assertSessionHas('success');

    $this->tenant->run(function () {
        expect(Customer::where('name', 'Globex')->exists())->toBeTrue();
    });
});

it('rejects a duplicate customer email', function () {
    $this->tenant->run(fn () => Customer::create(['name' => 'Globex', 'email' => 'dup@x.test']));

    loginAsAcmeUser();

    $this->from('/acme/customers')
        ->post('/acme/customers', ['name' => 'Other', 'email' => 'dup@x.test'])
        ->assertRedirect('/acme/customers')
        ->assertSessionHasErrors('email');
});

it('allows multiple customers with no email', function () {
    loginAsAcmeUser();

    $this->post('/acme/customers', ['name' => 'No Email One'])->assertSessionHasNoErrors();
    $this->post('/acme/customers', ['name' => 'No Email Two'])->assertSessionHasNoErrors();

    $this->tenant->run(function () {
        expect(Customer::whereNull('email')->count())->toBe(2);
    });
});

it('updates a customer', function () {
    $id = $this->tenant->run(fn () => Customer::create(['name' => 'Globex'])->id);

    loginAsAcmeUser();

    $this->from('/acme/customers')
        ->put("/acme/customers/{$id}", ['name' => 'Globex Corp'])
        ->assertRedirect('/acme/customers')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(Customer::find($id)->name)->toBe('Globex Corp');
    });
});

it('soft-deletes a customer', function () {
    $id = $this->tenant->run(fn () => Customer::create(['name' => 'Globex'])->id);

    loginAsAcmeUser();

    $this->from('/acme/customers')
        ->delete("/acme/customers/{$id}")
        ->assertRedirect('/acme/customers')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(Customer::find($id))->toBeNull()
            ->and(Customer::withTrashed()->find($id))->not->toBeNull();
    });
});

it('searches customers by name or email', function () {
    $this->tenant->run(function () {
        Customer::create(['name' => 'Globex', 'email' => 'buyer@globex.test']);
        Customer::create(['name' => 'Initech', 'email' => 'ap@initech.test']);
    });

    loginAsAcmeUser();

    $this->get('/acme/customers?search=ap@initech')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Initech')
        );
});
