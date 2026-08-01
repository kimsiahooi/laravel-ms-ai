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
            'contact_person' => 'Jane Tan',
            'email' => 'metals@acme.test',
            'tax_id' => 'C1234567890',
            'phone' => '+60 12-345 6789',
            'address' => '1 Foundry Rd',
            'notes' => 'Primary steel supplier',
        ])
        ->assertRedirect('/acme/suppliers')
        ->assertToast('Supplier created.');

    $this->tenant->run(function () {
        $supplier = Supplier::firstWhere('name', 'Acme Metals');
        expect($supplier)->not->toBeNull()
            ->and($supplier->contact_person)->toBe('Jane Tan')
            ->and($supplier->tax_id)->toBe('C1234567890');
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
        ->assertToast('Supplier updated.');

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
        ->assertToast('Supplier deleted.');

    $this->tenant->run(function () use ($id) {
        expect(Supplier::find($id))->toBeNull()
            ->and(Supplier::withTrashed()->find($id))->not->toBeNull();
    });
});

it('searches suppliers by name or email', function () {
    $this->tenant->run(function () {
        Supplier::create(['name' => 'Acme Metals', 'email' => 'metals@acme.test']);
        Supplier::create(['name' => 'Bolt Co', 'email' => 'sales@bolt.test']);
    });

    loginAsAcmeUser();

    $this->get('/acme/suppliers?search=sales@bolt')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.name', 'Bolt Co')
        );
});

it('searches suppliers by their notes (R04)', function () {
    $this->tenant->run(function () {
        Supplier::create(['name' => 'Acme Metals', 'notes' => 'Bought TV brackets, kettles, air fryers']);
        Supplier::create(['name' => 'Bolt Co', 'notes' => 'Fasteners only']);
    });

    loginAsAcmeUser();

    $this->get('/acme/suppliers?search=kettle')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.name', 'Acme Metals')
        );
});

it('sorts suppliers by a whitelisted column, echoing the sort in filters (R06)', function () {
    $this->tenant->run(function () {
        Supplier::create(['name' => 'Zeta Supplies']);
        Supplier::create(['name' => 'Alpha Metals']);
    });

    loginAsAcmeUser();

    $this->get('/acme/suppliers?sort=name&direction=asc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('suppliers.data.0.name', 'Alpha Metals')
            ->where('suppliers.data.1.name', 'Zeta Supplies')
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc')
        );
});

it('ignores an unknown sort column, falling back to the default (R06)', function () {
    $this->tenant->run(function () {
        Supplier::create(['name' => 'Acme']);
    });

    loginAsAcmeUser();

    // A hand-typed column not in the allow-list must not reach the query.
    $this->get('/acme/suppliers?sort=notes&direction=asc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'created_at')
        );
});

it('stamps the creator on a new supplier and shows it on the list (R06b)', function () {
    loginAsAcmeUser();

    $this->post('/acme/suppliers', ['name' => 'Acme Metals'])
        ->assertRedirect();

    $this->tenant->run(function () {
        $supplier = Supplier::with('creator')->firstOrFail();
        expect($supplier->created_by)->not->toBeNull()
            ->and($supplier->creator?->name)->toBe('Ada');
    });

    $this->get('/acme/suppliers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('suppliers.data.0.creator', 'Ada')
        );
});
