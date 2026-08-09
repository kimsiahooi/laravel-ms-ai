<?php

use App\Actions\ProvisionTenant;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\RawMaterial;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\StockTake;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantPermissions;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

/**
 * One real row per model-bound route parameter. Route-model binding runs *before*
 * the permission gate (SubstituteBindings is registered ahead of
 * AuthorizeTenantRoute), so a made-up id would 404 before it could ever 403 —
 * which would make this whole sweep pass for the wrong reason.
 *
 * @return array<string, int|string> route parameter name => value
 */
function gateFixtureIds(): array
{
    return test()->tenant->run(function (): array {
        $location = Location::create(['name' => 'KL HQ']);
        $warehouse = Warehouse::create(['location_id' => $location->id, 'name' => 'Main']);
        $product = Product::create(['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs']);

        return [
            'tenant' => 'acme',
            'category' => Category::create(['name' => 'Parts'])->id,
            'supplier' => Supplier::create(['name' => 'Supplier'])->id,
            'customer' => Customer::create(['name' => 'Customer'])->id,
            'rawMaterial' => RawMaterial::create(['name' => 'Steel', 'sku' => 'S-1', 'unit' => 'kg'])->id,
            'product' => $product->id,
            'location' => $location->id,
            'warehouse' => $warehouse->id,
            'stockTake' => StockTake::create(['warehouse_id' => $warehouse->id])->id,
            'purchaseOrder' => PurchaseOrder::create(['currency' => 'MYR'])->id,
            'purchaseReturn' => PurchaseReturn::create([])->id,
            'salesOrder' => SalesOrder::create(['currency' => 'MYR'])->id,
            'salesReturn' => SalesReturn::create([])->id,
            'productionOrder' => ProductionOrder::create([
                'product_id' => $product->id,
                'product_snapshot' => ['name' => 'Widget', 'sku' => 'W-1', 'unit' => 'pcs'],
                'quantity' => 1,
            ])->id,
            'role' => Role::findOrCreate('Gate probe', 'web')->id,
            // Ada, never the logged-in member — so a walked DELETE can't sign the
            // member out and turn every later 403 into a redirect.
            'user' => User::where('email', 'ada@acme.test')->value('id'),
        ];
    });
}

/**
 * Every permission-gated tenant route as [method, concrete URI, permission],
 * derived from the router + the permission catalog rather than a hand-kept list —
 * so a newly added gated route is swept the moment it is registered.
 *
 * @param  array<string, int|string>  $ids
 * @return list<array{0: string, 1: string, 2: string}>
 */
function gatedTenantRoutes(array $ids): array
{
    $map = TenantPermissions::routeMap();
    $routes = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();
        if ($name === null || ! str_starts_with($name, 'tenant.')) {
            continue;
        }
        $short = substr($name, strlen('tenant.'));

        // Export is mapped dynamically by the middleware ({resource}.view); walk it
        // as the products export.
        $permission = $short === 'export' ? 'products.view' : ($map[$short] ?? null);
        if ($permission === null) {
            continue;   // dashboard, media, stock lookups, logout — deliberately open
        }

        $uri = $route->uri();
        foreach ($route->parameterNames() as $parameter) {
            // {category} is a model id on categories.*, but a settings *category*
            // on settings.* — resolve per route, never per parameter name.
            $value = match (true) {
                str_starts_with($short, 'settings.') && $parameter === 'category' => 'business',
                $short === 'export' => $parameter === 'resource' ? 'products' : ($ids[$parameter] ?? 1),
                default => $ids[$parameter] ?? 1,
            };
            $uri = str_replace(['{'.$parameter.'}', '{'.$parameter.'?}'], (string) $value, $uri);
        }

        $method = collect($route->methods())->first(fn (string $m): bool => $m !== 'HEAD');
        $routes[] = [(string) $method, '/'.ltrim($uri, '/'), $permission];
    }

    return $routes;
}

/** Re-point the Member role at exactly one permission (no re-login needed). */
function grantMemberOnly(string $permission): void
{
    test()->tenant->run(function () use ($permission) {
        Role::where('name', 'Member')->firstOrFail()->syncPermissions([$permission]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    });
}

it('403s every permission-gated route for a member holding no permissions', function () {
    $ids = gateFixtureIds();
    loginAsAcmeMember([]);

    // One test with an internal loop, not a dataset: a dataset would re-provision
    // the tenant for all 90 rows. Collect every offender so the failure names them.
    $leaked = [];
    foreach (gatedTenantRoutes($ids) as [$method, $uri, $permission]) {
        $status = $this->call($method, $uri)->getStatusCode();

        if ($status !== 403) {
            $leaked[] = "{$method} {$uri} (needs {$permission}) returned {$status}";
        }
    }

    expect($leaked)->toBe([], 'Routes that are not gated:'.PHP_EOL.implode(PHP_EOL, $leaked));
});

it('lets a member through each gated route once granted exactly that permission', function () {
    $ids = gateFixtureIds();
    loginAsAcmeMember([]);

    // The mirror of the sweep above: proves each route is mapped to the permission
    // that actually opens it, so the gate can't be mis-wired to the wrong one.
    $denied = [];
    foreach (gatedTenantRoutes($ids) as [$method, $uri, $permission]) {
        grantMemberOnly($permission);

        // Not-403 is the whole assertion — a 302/404/422 from the controller still
        // proves the gate let the request past with the right permission.
        if ($this->call($method, $uri)->getStatusCode() === 403) {
            $denied[] = "{$method} {$uri} still 403s with {$permission}";
        }
    }

    expect($denied)->toBe([], 'Routes mapped to the wrong permission:'.PHP_EOL.implode(PHP_EOL, $denied));
});

it('sweeps every gated route, so the sweep cannot quietly cover nothing', function () {
    // Guard the guard: if routeMap() or the route names ever drift, the two sweeps
    // above would pass by walking an empty list. Update this deliberately.
    expect(gatedTenantRoutes(gateFixtureIds()))->toHaveCount(90);
});
