<?php

use App\Actions\ProvisionTenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

dataset('new tenant screens', [
    ['stock-takes', 'tenant/stock-takes/index'],
    ['purchase-returns', 'tenant/purchase-returns/index'],
    ['sales-returns', 'tenant/sales-returns/index'],
    ['reports', 'tenant/reports/index'],
    ['activity', 'tenant/activity/index'],
]);

it('renders each new tenant screen for an authenticated user', function (string $path, string $component) {
    loginAsAcmeUser();

    $this->get("/acme/{$path}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with('new tenant screens');
