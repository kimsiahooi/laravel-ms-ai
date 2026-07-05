<?php

use App\Actions\ProvisionTenant;
use App\Models\Category;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme',
        'acme',
        'Ada',
        'ada@acme.test',
        'password123',
    );
});

function loginAsAcmeUser(): void
{
    test()->post('/acme/login', [
        'email' => 'ada@acme.test',
        'password' => 'password123',
    ]);
}

it('redirects a guest from the categories page to the tenant login', function () {
    $this->get('/acme/categories')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('lists a tenant’s categories, paginated', function () {
    $this->tenant->run(function () {
        Category::create(['name' => 'Fasteners']);
        Category::create(['name' => 'Adhesives']);
    });

    loginAsAcmeUser();

    $this->get('/acme/categories?per_page=10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/categories/index')
            ->has('categories.data', 2)
            ->where('categories.total', 2)
            ->where('filters.per_page', 10)
        );
});

it('creates a category', function () {
    loginAsAcmeUser();

    $this->from('/acme/categories')
        ->post('/acme/categories', [
            'name' => 'Fasteners',
            'description' => 'Bolts, nuts, screws',
        ])
        ->assertRedirect('/acme/categories')
        ->assertSessionHas('success');

    $this->tenant->run(function () {
        expect(Category::where('name', 'Fasteners')->exists())->toBeTrue();
    });
});

it('rejects a duplicate category name', function () {
    $this->tenant->run(fn () => Category::create(['name' => 'Fasteners']));

    loginAsAcmeUser();

    $this->from('/acme/categories')
        ->post('/acme/categories', ['name' => 'Fasteners'])
        ->assertRedirect('/acme/categories')
        ->assertSessionHasErrors('name');
});

it('updates a category', function () {
    $id = $this->tenant->run(
        fn () => Category::create(['name' => 'Fasteners'])->id,
    );

    loginAsAcmeUser();

    $this->from('/acme/categories')
        ->put("/acme/categories/{$id}", ['name' => 'Hardware'])
        ->assertRedirect('/acme/categories')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(Category::find($id)->name)->toBe('Hardware');
    });
});

it('soft-deletes a category', function () {
    $id = $this->tenant->run(
        fn () => Category::create(['name' => 'Fasteners'])->id,
    );

    loginAsAcmeUser();

    $this->from('/acme/categories')
        ->delete("/acme/categories/{$id}")
        ->assertRedirect('/acme/categories')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(Category::find($id))->toBeNull()
            ->and(Category::withTrashed()->find($id))->not->toBeNull();
    });
});
