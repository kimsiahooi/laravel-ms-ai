<?php

use App\Actions\ProvisionTenant;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\RawMaterial;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/** Route parameters that name a record, and so must 404 when it doesn't exist. */
const RECORD_PARAMETERS = [
    'category', 'supplier', 'customer', 'rawMaterial', 'product', 'location',
    'warehouse', 'stockTake', 'purchaseOrder', 'purchaseReturn', 'salesOrder',
    'salesReturn', 'productionOrder', 'role', 'user',
];

/**
 * Every tenant route that resolves a record, with the given id substituted in.
 * Derived from the router, so a new record route is swept automatically.
 *
 * @return list<array{0: string, 1: string}> [method, uri]
 */
function recordRoutes(int|string $id): array
{
    $routes = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();
        if ($name === null || ! str_starts_with($name, 'tenant.')) {
            continue;
        }
        $short = substr($name, strlen('tenant.'));

        // settings/{category} is a settings section and export/{resource} a registry
        // key — neither is a record, and both 404 on their own terms elsewhere.
        $parameters = array_values(array_intersect($route->parameterNames(), RECORD_PARAMETERS));
        if ($parameters === [] || str_starts_with($short, 'settings.')) {
            continue;
        }

        $uri = str_replace('{tenant}', 'acme', $route->uri());
        foreach ($parameters as $parameter) {
            $uri = str_replace('{'.$parameter.'}', (string) $id, $uri);
        }

        $method = collect($route->methods())->first(fn (string $m): bool => $m !== 'HEAD');
        $routes[] = [(string) $method, '/'.ltrim($uri, '/')];
    }

    return $routes;
}

it('404s every record route for an id that does not exist', function () {
    loginAsAcmeUser();

    // One loop, not a dataset — a dataset would re-provision the tenant per route.
    $wrong = [];
    foreach (recordRoutes(999_999) as [$method, $uri]) {
        $status = $this->call($method, $uri)->getStatusCode();

        if ($status !== 404) {
            $wrong[] = "{$method} {$uri} returned {$status}";
        }
    }

    expect($wrong)->toBe([], 'Routes that did not 404 for a missing record:'.PHP_EOL.implode(PHP_EOL, $wrong));
});

it('sweeps every record route, so the sweep cannot quietly cover nothing', function () {
    // Guard the guard: without this, a rename that empties the list would let the
    // sweep above pass while testing nothing at all.
    expect(recordRoutes(1))->toHaveCount(51);
});

it('404s a soft-deleted record instead of quietly serving it', function () {
    // Every trashable resource, with the route segment it lives under.
    $trashed = $this->tenant->run(function (): array {
        $location = Location::create(['name' => 'KL HQ']);

        $records = [
            'categories' => Category::create(['name' => 'Parts']),
            'suppliers' => Supplier::create(['name' => 'Supplier']),
            'customers' => Customer::create(['name' => 'Customer']),
            'raw-materials' => RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg']),
            'products' => Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']),
            'warehouses' => Warehouse::create(['location_id' => $location->id, 'name' => 'Main']),
            'purchase-orders' => PurchaseOrder::create(['currency' => 'MYR']),
            'purchase-returns' => PurchaseReturn::create([]),
            'sales-orders' => SalesOrder::create(['currency' => 'MYR']),
            'sales-returns' => SalesReturn::create([]),
            'locations' => $location,
        ];

        $ids = [];
        foreach ($records as $segment => $record) {
            $record->delete();
            $ids[$segment] = $record->id;
        }

        return $ids;
    });

    loginAsAcmeUser();

    $served = [];
    foreach ($trashed as $segment => $id) {
        foreach (recordRoutes($id) as [$method, $uri]) {
            if (! str_starts_with($uri, "/acme/{$segment}/")) {
                continue;
            }

            $status = $this->call($method, $uri)->getStatusCode();
            if ($status !== 404) {
                $served[] = "{$method} {$uri} returned {$status} for a deleted record";
            }
        }
    }

    expect($served)->toBe([], 'Deleted records still reachable:'.PHP_EOL.implode(PHP_EOL, $served));
});
