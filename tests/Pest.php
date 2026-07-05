<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Insert tenant rows WITHOUT firing TenantCreated, so list/stat tests don't
 * provision (and later drop) a real database per row.
 */
function makeTenants(int $count): void
{
    Tenant::withoutEvents(function () use ($count) {
        foreach (range(1, $count) as $i) {
            Tenant::create(['name' => "Company {$i}", 'id' => "co-{$i}"]);
        }
    });
}

/**
 * Log in as the seeded first user of the `acme` tenant (provision it first
 * via ProvisionTenant in the test's beforeEach).
 */
function loginAsAcmeUser(): void
{
    test()->post('/acme/login', [
        'email' => 'ada@acme.test',
        'password' => 'password123',
    ]);
}
