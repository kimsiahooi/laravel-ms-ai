<?php

use App\Actions\ProvisionTenant;
use App\Models\Product;
use App\Models\SalesOrder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('never serves another tenant’s record, even by an id that really exists there', function () {
    $globex = app(ProvisionTenant::class)->handle(
        'Globex', 'globex', 'Greg', 'greg@globex.test', 'password123',
    );

    // Acme gets one order; Globex gets three. Id 3 is a REAL id in Globex and must
    // be invisible from Acme, while id 1 exists in *both* and must resolve to
    // Acme's row — the two ways a leak between tenant databases would show up.
    $acmeOrder = $this->tenant->run(
        fn (): int => SalesOrder::create(['currency' => 'MYR'])->id,
    );
    $globexOnly = $globex->run(function (): int {
        $ids = collect(range(1, 3))
            ->map(fn (): int => SalesOrder::create(['currency' => 'SGD'])->id);

        return (int) $ids->last();
    });

    expect($globexOnly)->toBeGreaterThan($acmeOrder);

    loginAsAcmeUser();

    $this->get("/acme/sales-orders/{$globexOnly}")->assertNotFound();
    $this->put("/acme/sales-orders/{$globexOnly}", [])->assertNotFound();
    $this->delete("/acme/sales-orders/{$globexOnly}")->assertNotFound();
    $this->post("/acme/sales-orders/{$globexOnly}/cancel")->assertNotFound();
    $this->get("/acme/sales-orders/{$globexOnly}/e-invoice")->assertNotFound();

    // The shared id resolves to Acme's own row, in Acme's currency…
    $this->get("/acme/sales-orders/{$acmeOrder}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('order.currency', 'MYR'));

    // …and neither database grew a row belonging to the other.
    $this->tenant->run(fn () => expect(SalesOrder::count())->toBe(1));
    $globex->run(fn () => expect(SalesOrder::count())->toBe(3));
});

it('keeps each tenant’s catalog to itself', function () {
    $globex = app(ProvisionTenant::class)->handle(
        'Globex', 'globex', 'Greg', 'greg@globex.test', 'password123',
    );

    $this->tenant->run(fn () => Product::create(['name' => 'Acme Widget', 'sku' => 'A-1', 'unit' => 'pcs']));

    // Give Globex more rows than Acme, so the id we probe with is real in Globex
    // and out of range in Acme — otherwise both tenants' first row is id 1 and the
    // probe would only prove that Acme's own record answers.
    $globexProduct = $globex->run(function (): int {
        $ids = collect(range(1, 3))->map(
            fn (int $n): int => Product::create([
                'name' => "Globex Gadget {$n}", 'sku' => "G-{$n}", 'unit' => 'pcs',
            ])->id,
        );

        return (int) $ids->last();
    });

    loginAsAcmeUser();

    // Acme's list shows only Acme's product…
    $this->get('/acme/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Acme Widget')
        );

    // …and a real Globex id is simply not there as far as Acme is concerned.
    $this->put("/acme/products/{$globexProduct}", ['name' => 'Stolen'])->assertNotFound();
    $this->delete("/acme/products/{$globexProduct}")->assertNotFound();

    $globex->run(fn () => expect(Product::count())->toBe(3)
        ->and(Product::find(3)->name)->toBe('Globex Gadget 3'));
});
