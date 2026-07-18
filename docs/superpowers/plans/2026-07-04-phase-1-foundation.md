# Phase 1 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the Laravel 13 + Inertia v3 + React app with archtechx/tenancy in multi-database mode, path-identified by org slug, where a central super-admin can provision a tenant (create its database, migrate, seed the first user) and tenant users log in only to their own tenant with fully isolated sessions.

**Architecture:** One central (landlord) database holds super-admin `users` and the `organizations` (tenants) table. Each organization gets its own database (auto-created on `Organization::create`) holding that tenant's `users` + business tables. Routes under `/{tenant}/…` run `InitializeTenancyByPath`, which switches the default DB connection to the tenant; the `web` guard then authenticates against the tenant's `users`. `/admin/…` stays on the central connection with a separate `central` guard. Session isolation is achieved with the database session driver (follows the switched connection) plus a per-tenant session cookie.

**Tech Stack:** Laravel 13.18+, PHP 8.3+, `stancl/tenancy` ^3.10 (multi-DB), Inertia.js v3 (`@inertiajs/react` ^3, `inertiajs/inertia-laravel` ^3), React 19 + TypeScript, Tailwind v4, Laravel Fortify (auth, from the React starter kit), laravel/wayfinder (typed routes), Pest (tests), MySQL.

---

## Decisions refined from live-doc research (2026-07-04)

- **Tenancy version floor:** `stancl/tenancy:^3.10` — the first tag that allows `illuminate/support: ^13`. Any tag ≤ 3.9.1 refuses to install on Laravel 13. **Fallback only if ^3.10 cannot resolve:** stay on Laravel 12 + tenancy ^3.9 (should not be necessary).
- **Slug identification = Approach B (resolver subclass).** `organizations.id` stays the tenant key (stable `tenant_<id>` DB names); a `PathSlugTenantResolver` resolves the `/{tenant}` segment by the `slug` column. Chosen over `getTenantKeyName()='slug'` so renaming a slug never orphans a database. *(This refines spec decision #7: the org id is a plain auto-increment bigint, not a ULID.)*
- **Routing helper = laravel/wayfinder** (the React starter kit ships it), **not Ziggy.** Disabling a Fortify feature means also removing its frontend route refs or `npm run build` fails.
- **Scaffold safely:** do **NOT** use `laravel new --force` (it `rm -rf`s the target). Move `docs/` aside, scaffold into the empty dir, move `docs/` back.
- **Session driver = database**, `SESSION_CONNECTION=null` (follows the switched connection). A `sessions` migration must exist in **both** `database/migrations/` and `database/migrations/tenant/`.

## Prerequisite check (do first, no code)

- [ ] **Verify toolchain.** Run each; all must succeed:

```bash
php -v            # expect PHP 8.3.x or newer
composer -V       # expect Composer 2.x
node -v           # expect Node 20+ (for Vite 6 / Tailwind v4)
npm -v
mysql --version   # a reachable MySQL 8 server is required for multi-DB provisioning
laravel -V 2>/dev/null || composer global require laravel/installer   # install the installer if missing
```

Expected: versions print; if `laravel` is missing, the last command installs it. If PHP < 8.3, stop and upgrade — the React starter kit pins `php: ^8.3`.

- [ ] **Confirm a MySQL user that can CREATE DATABASE** exists (tenancy provisions real databases). Note its credentials for `.env` in Task 3.

---

## Task 1: Scaffold the Laravel 13 React starter kit ✅ DONE (2026-07-04, this session)

> **Completed with three approved deviations from the original text below:**
> - **Package manager = Bun**, not npm → scaffolded with the installer's `--bun` flag. `bun.lock` is committed; no `package-lock.json`.
> - **Laravel Boost added** via `--boost` (AI-assist tooling: `AGENTS.md`, `boost.json`, `.agents/`).
> - **Standalone git repo:** `~/Herd` is itself an (empty, no-commits) git super-repo containing every project, so this app got its **own** repo via `git init -b main` inside `~/Herd/laravel-ms-ai`. The installer does **not** git-init without `--git`. All commits stay scoped to this project.
>
> **Actual command run:** `laravel new laravel-ms-ai --react --database=mysql --pest --bun --boost --no-interaction`
> **Verified:** Laravel 13.18.1 · `@inertiajs/react` ^3.0 · `inertiajs/inertia-laravel` ^3.0 · React 19.2 · TS 5.7 · Tailwind v4 · Vite 8 · Fortify ^1.37 · Wayfinder ^0.1.14 · Pest ^4.7 · Boost ^2.2. `bun run build` green (1.25s); Fortify/passkey auth routes present.

**Files:**
- Preserve: `docs/` (the specs + plans already written)
- Create: the entire Laravel app tree in `/Users/jasonooi/Herd/laravel-ms-ai`

- [x] **Step 1: Move `docs/` out of the way** (the installer needs an empty target)

```bash
mv /Users/jasonooi/Herd/laravel-ms-ai/docs \
   "/private/tmp/claude-501/-Users-jasonooi-Documents-laravel-ms-ai/3371a385-cf7e-4cd9-85dd-8ee283a81540/scratchpad/docs-backup"
cd /Users/jasonooi/Herd
rmdir laravel-ms-ai   # remove the now-empty dir so the installer can create it fresh
```

- [x] **Step 2: Scaffold the React starter kit**

```bash
cd /Users/jasonooi/Herd
laravel new laravel-ms-ai --react --database=mysql --pest --bun --boost --no-interaction
```

Expected: installer downloads the React starter kit (Inertia v3, React 19, TS, Tailwind v4, Fortify, Wayfinder), runs `composer install`, `bun install`, and `bun run build`. It does **not** run `git init` (that requires the `--git` flag; we init a standalone repo ourselves in Step 5). `--bun` uses Bun as the package manager/builder; `--boost` adds Laravel Boost. Flag notes: `--react` = React kit; `--database=mysql` (NOT sqlite — multi-DB needs real databases); `--pest` = Pest tests; do not pass `--workos` (it strips password/registration auth we need) or `--teams` (Fortify teams, unrelated to tenancy).

- [x] **Step 3: Restore `docs/`**

```bash
mv "/private/tmp/claude-501/-Users-jasonooi-Documents-laravel-ms-ai/3371a385-cf7e-4cd9-85dd-8ee283a81540/scratchpad/docs-backup" \
   /Users/jasonooi/Herd/laravel-ms-ai/docs
```

- [x] **Step 4: Verify the app boots**

```bash
cd /Users/jasonooi/Herd/laravel-ms-ai
php artisan --version         # expect "Laravel Framework 13.x"
grep '"@inertiajs/react"' package.json   # expect "^3.x"
grep 'laravel/wayfinder' composer.json   # expect present
php artisan route:list | head            # expect Fortify auth routes (login, register, ...)
```

Expected: Laravel 13.x, Inertia react ^3, wayfinder present, Fortify routes listed.

- [x] **Step 5: Commit** — standalone repo first (`~/Herd` is a super-repo)

```bash
git init -b main   # this project gets its OWN repo, isolated from the ~/Herd super-repo
git add -A && git commit -m "chore: scaffold Laravel 13 React starter kit (Inertia v3, React 19, TS) with Bun + Boost"
```

---

## Task 2: Install & verify stancl/tenancy on Laravel 13 (highest-risk item — do early)

**Files:**
- Modify: `composer.json` / `composer.lock`
- Create: `config/tenancy.php`, `app/Providers/TenancyServiceProvider.php`, `routes/tenant.php`, `database/migrations/tenant/`, central `*_create_tenants_table` + `*_create_domains_table` migrations

- [ ] **Step 1: Require the package at the version floor**

```bash
composer require stancl/tenancy:^3.10
```

Expected: resolves to ≥ 3.10.0 with no `illuminate/support` conflict. **If it fails** with a version conflict, capture the error and STOP — this is the one hard blocker; the fallback is Laravel 12 + tenancy ^3.9 (a scope change to escalate to the user, not to silently apply).

- [ ] **Step 2: Run the tenancy installer**

```bash
php artisan tenancy:install
```

Expected: publishes `config/tenancy.php`, `app/Providers/TenancyServiceProvider.php`, `routes/tenant.php`, the central `create_tenants_table` + `create_domains_table` migrations, and creates `database/migrations/tenant/`.

- [ ] **Step 3: Verify the provider is registered** (Laravel 13 uses `bootstrap/providers.php`, no `config/app.php` providers array)

Check `bootstrap/providers.php` contains `App\Providers\TenancyServiceProvider::class`. If absent, add it:

```php
// bootstrap/providers.php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
];
```

- [ ] **Step 4: Delete the unused domains migration** (we use path identification, not domains)

```bash
rm database/migrations/*_create_domains_table.php
```

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(tenancy): install stancl/tenancy ^3.10 (multi-DB), remove domains migration"
```

---

## Task 3: Central database connection + env

**Files:**
- Modify: `config/database.php`, `.env`, `.env.example`

- [ ] **Step 1: Name the primary connection `central`**

In `config/database.php`, duplicate the `mysql` connection as `central` (or rename), and set the default:

```php
'default' => env('DB_CONNECTION', 'central'),

'connections' => [
    'central' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'laravel_ms_ai_central'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
    ],
    // ... keep the rest; NEVER define a connection literally named 'tenant'
],
```

- [ ] **Step 2: Point env at the central connection**

```dotenv
DB_CONNECTION=central
DB_DATABASE=laravel_ms_ai_central
DB_USERNAME=<mysql user that can CREATE DATABASE>
DB_PASSWORD=<password>

SESSION_DRIVER=database
SESSION_CONNECTION=null
```

Mirror the non-secret keys into `.env.example`.

- [ ] **Step 3: Confirm tenancy central connection config**

In `config/tenancy.php`, verify `'database' => ['central_connection' => env('DB_CONNECTION', 'central'), 'template_tenant_connection' => null, ...]`. Leave `template_tenant_connection` null (falls back to central as the template).

- [ ] **Step 4: Create the central database and verify connection**

```bash
mysql -u <user> -p -e "CREATE DATABASE IF NOT EXISTS laravel_ms_ai_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan db:show --database=central   # expect it connects and lists the central DB
```

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(db): name primary connection 'central', database session driver"
```

---

## Task 4: `organizations` table + `Organization` tenant model

**Files:**
- Rename/replace: the published `*_create_tenants_table.php` → `*_create_organizations_table.php`
- Create: `app/Models/Organization.php`
- Test: `tests/Feature/OrganizationModelTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/OrganizationModelTest.php
use App\Models\Organization;

it('creates an organization on the central connection with a stable numeric id and slug', function () {
    $org = Organization::create(['name' => 'Acme Co', 'slug' => 'acme']);

    expect($org->getConnectionName())->toBe('central')
        ->and($org->getKeyName())->toBe('id')
        ->and($org->id)->toBeInt()
        ->and($org->slug)->toBe('acme')
        ->and($org->getTable())->toBe('organizations');
});
```

- [ ] **Step 2: Run it — expect failure**

```bash
php artisan test --filter=OrganizationModelTest
```

Expected: FAIL (no `Organization` model / no `organizations` table yet).

- [ ] **Step 3: Rewrite the tenants migration as `organizations`**

Rename the file to `..._create_organizations_table.php` and set its body:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();                       // stable numeric tenant key
            $table->string('name');
            $table->string('slug')->unique();   // path identifier
            $table->string('logo')->nullable();
            $table->json('data')->nullable();   // stancl HasDataColumn overflow + tenancy_db_* keys
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('organizations'); }
};
```

- [ ] **Step 4: Create the model**

```php
<?php // app/Models/Organization.php
namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Organization extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, SoftDeletes;

    protected $table = 'organizations';

    // Real columns; everything else overflows into the json `data` column.
    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'slug', 'logo'];
    }
}
```

- [ ] **Step 5: Point config at the model + disable UUID id generation**

In `config/tenancy.php`:

```php
'tenant_model' => \App\Models\Organization::class,
'id_generator' => null,   // keep auto-increment integer ids (no UUID)
```

- [ ] **Step 6: Migrate central + run the test**

```bash
php artisan migrate
php artisan test --filter=OrganizationModelTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat(tenancy): Organization tenant model on organizations table (numeric id + slug)"
```

---

## Task 5: Resolve the `/{tenant}` path segment by `slug` (Approach B)

**Files:**
- Create: `app/Tenancy/PathSlugTenantResolver.php`
- Modify: `app/Providers/TenancyServiceProvider.php` (bind the resolver)
- Test: `tests/Feature/PathSlugResolverTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/PathSlugResolverTest.php
use App\Models\Organization;
use App\Tenancy\PathSlugTenantResolver;
use Illuminate\Routing\Route;

it('resolves a tenant from the slug path segment', function () {
    $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
    $route = new Route(['GET'], '/{tenant}/dashboard', fn () => null);
    $route->bind(request());
    $route->setParameter('tenant', 'acme');

    $resolved = app(PathSlugTenantResolver::class)->resolve($route);

    expect($resolved->getTenantKey())->toBe($org->getTenantKey());
});

it('throws when the slug has no organization', function () {
    $route = new Route(['GET'], '/{tenant}/dashboard', fn () => null);
    $route->bind(request());
    $route->setParameter('tenant', 'ghost');

    app(PathSlugTenantResolver::class)->resolve($route);
})->throws(\Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByPathException::class);
```

- [ ] **Step 2: Run it — expect failure**

```bash
php artisan test --filter=PathSlugResolverTest
```

Expected: FAIL (resolver class missing).

- [ ] **Step 3: Implement the resolver**

```php
<?php // app/Tenancy/PathSlugTenantResolver.php
namespace App\Tenancy;

use Illuminate\Routing\Route;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByPathException;
use Stancl\Tenancy\Resolvers\PathTenantResolver;

class PathSlugTenantResolver extends PathTenantResolver
{
    public function resolveWithoutCache(mixed ...$args): Tenant
    {
        /** @var Route $route */
        $route = $args[0];
        $slug = $route->parameter(static::tenantParameterName());

        // Do not leak the tenant param into controller args.
        $route->forgetParameter(static::tenantParameterName());

        if ($slug !== null) {
            $tenant = tenancy()->query()->where('slug', $slug)->first();
            if ($tenant) {
                return $tenant;
            }
        }

        throw new TenantCouldNotBeIdentifiedByPathException($slug);
    }
}
```

- [ ] **Step 4: Bind it so `InitializeTenancyByPath` uses it**

In `app/Providers/TenancyServiceProvider.php` `register()`:

```php
$this->app->bind(
    \Stancl\Tenancy\Resolvers\PathTenantResolver::class,
    \App\Tenancy\PathSlugTenantResolver::class,
);
```

- [ ] **Step 5: Run the test**

```bash
php artisan test --filter=PathSlugResolverTest
```

Expected: PASS (both cases).

> Verification note (research "biggest unknown"): the exact `resolveWithoutCache` signature is version-sensitive. If the test errors on the method signature, open `vendor/stancl/tenancy/src/Resolvers/PathTenantResolver.php`, match its `resolveWithoutCache`/`tenantParameterName` exactly, and adjust.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(tenancy): resolve tenant by slug path segment (Approach B)"
```

---

## Task 6: Central vs tenant `User` models + auth guards

**Files:**
- Modify: `app/Models/User.php` (tenant users — plain, default connection)
- Create: `app/Models/CentralUser.php` (super-admins — pinned to central)
- Modify: `config/auth.php`
- Test: `tests/Feature/AuthGuardsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/AuthGuardsTest.php
use App\Models\CentralUser;

it('pins central users to the central connection', function () {
    expect((new CentralUser)->getConnectionName())->toBe(config('tenancy.database.central_connection'))
        ->and((new CentralUser)->getTable())->toBe('users');
});

it('registers web (tenant) and central guards', function () {
    expect(config('auth.guards.web.provider'))->toBe('tenant_users')
        ->and(config('auth.guards.central.provider'))->toBe('central_users')
        ->and(config('auth.providers.tenant_users.model'))->toBe(App\Models\User::class)
        ->and(config('auth.providers.central_users.model'))->toBe(App\Models\CentralUser::class);
});
```

- [ ] **Step 2: Run it — expect failure**

```bash
php artisan test --filter=AuthGuardsTest
```

Expected: FAIL.

- [ ] **Step 3: Create `CentralUser`** (extends the starter kit's `User` shape but pins the connection)

```php
<?php // app/Models/CentralUser.php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class CentralUser extends Authenticatable
{
    use CentralConnection, Notifiable;

    protected $table = 'users';
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }
}
```

`App\Models\User` (from the starter kit) stays unchanged — a plain `Authenticatable` on the default connection, so it resolves against whichever DB is active (the tenant DB after `InitializeTenancyByPath`).

- [ ] **Step 4: Define the guards** in `config/auth.php`

```php
'defaults' => ['guard' => 'web', 'passwords' => 'users'],

'guards' => [
    'web'     => ['driver' => 'session', 'provider' => 'tenant_users'],
    'central' => ['driver' => 'session', 'provider' => 'central_users'],
],

'providers' => [
    'tenant_users'  => ['driver' => 'eloquent', 'model' => App\Models\User::class],
    'central_users' => ['driver' => 'eloquent', 'model' => App\Models\CentralUser::class],
],
```

- [ ] **Step 5: Run the test**

```bash
php artisan test --filter=AuthGuardsTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(auth): central vs tenant user models and guards"
```

---

## Task 7: Tenant route group with reserved-slug guard

**Files:**
- Modify: `routes/tenant.php`
- Modify: `bootstrap/app.php` (ensure `routes/tenant.php` is loaded) or `app/Providers/TenancyServiceProvider.php`
- Create: `app/Support/ReservedSlugs.php`
- Test: `tests/Feature/TenantRouteTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/TenantRouteTest.php
use App\Models\Organization;

it('serves a tenant route resolved by slug', function () {
    Organization::create(['name' => 'Acme', 'slug' => 'acme']);
    // a throwaway probe route registered in routes/tenant.php returns the active tenant slug
    $this->get('/acme/_probe')->assertOk()->assertSee('acme');
});

it('does not treat a reserved slug as a tenant', function () {
    $this->get('/admin/_probe')->assertStatus(404); // 'admin' excluded by the {tenant} pattern
});
```

- [ ] **Step 2: Run it — expect failure**

```bash
php artisan test --filter=TenantRouteTest
```

Expected: FAIL.

- [ ] **Step 3: Reserved-slug list**

```php
<?php // app/Support/ReservedSlugs.php
namespace App\Support;

class ReservedSlugs
{
    public const LIST = ['admin', 'login', 'register', 'central', 'api', 'assets', 'build', 'storage', 'up', 'sanctum'];

    public static function pattern(): string
    {
        return '(?!(' . implode('|', self::LIST) . ')$)[A-Za-z0-9][A-Za-z0-9-]*';
    }
}
```

- [ ] **Step 4: Configure `routes/tenant.php`**

```php
<?php // routes/tenant.php
use App\Support\ReservedSlugs;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

Route::pattern('tenant', ReservedSlugs::pattern());

Route::middleware(['web', InitializeTenancyByPath::class])
    ->prefix('/{tenant}')
    ->group(function () {
        Route::get('/_probe', fn () => tenant('slug')); // throwaway; removed in Task 13
    });
```

- [ ] **Step 5: Ensure `routes/tenant.php` is registered.** In `bootstrap/app.php` `->withRouting(...)`, add a `then:` callback (or confirm `TenancyServiceProvider` maps it). Simplest is explicit:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        Route::middleware('web')->group(base_path('routes/tenant.php'));
    },
)
```

(Import `use Illuminate\Support\Facades\Route;` at the top of `bootstrap/app.php`.)

- [ ] **Step 6: Run the test**

```bash
php artisan test --filter=TenantRouteTest
```

Expected: PASS. If the reserved-slug case fails, confirm the `{tenant}` pattern is applied (it must be declared before the group).

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat(tenancy): path tenant route group + reserved-slug guard"
```

---

## Task 8: Middleware priority — `InitializeTenancyByPath` before `StartSession`

**Files:**
- Test: `tests/Feature/MiddlewarePriorityTest.php`
- Modify: `bootstrap/app.php` only if the assertion fails

- [ ] **Step 1: Write the verification test** (research flagged this as the #1 footgun; verify, don't assume)

```php
<?php // tests/Feature/MiddlewarePriorityTest.php
use App\Models\Organization;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

it('runs tenancy initialization before the session starts on tenant routes', function () {
    Organization::create(['name' => 'Acme', 'slug' => 'acme']);

    // Probe route records middleware order via a marker the session would overwrite.
    $this->get('/acme/_probe')->assertOk();

    // The framework's sorted priority list must place Initialize* before StartSession.
    $priority = app(Illuminate\Contracts\Http\Kernel::class)->getMiddlewarePriority();
    $init = array_search(InitializeTenancyByPath::class, $priority, true);
    $start = array_search(Illuminate\Session\Middleware\StartSession::class, $priority, true);

    expect($init)->not->toBeFalse();
    expect($start)->not->toBeFalse();
    expect($init)->toBeLessThan($start);
});
```

- [ ] **Step 2: Run it**

```bash
php artisan test --filter=MiddlewarePriorityTest
```

Expected: PASS if `TenancyServiceProvider::makeTenancyMiddlewareHighestPriority()` fired. **If it FAILS**, add an explicit priority prepend in `bootstrap/app.php` `->withMiddleware(function ($middleware) { ... })`:

```php
$middleware->prependToPriorityList(
    before: Illuminate\Session\Middleware\StartSession::class,
    prepend: Stancl\Tenancy\Middleware\InitializeTenancyByPath::class,
);
```

Re-run until green. Do NOT call `$middleware->priority([...])` (it clobbers tenancy's prepend).

- [ ] **Step 3: Commit**

```bash
git add -A && git commit -m "test(tenancy): assert tenancy init precedes session start"
```

---

## Task 9: Session isolation — tenant `sessions` table + per-tenant cookie bootstrapper

**Files:**
- Create: central `sessions` migration (via `php artisan session:table`)
- Copy: `database/migrations/tenant/<ts>_create_sessions_table.php`
- Create: `app/Tenancy/SessionTenancyBootstrapper.php`
- Modify: `config/tenancy.php` (register the bootstrapper)
- Test: `tests/Feature/SessionIsolationTest.php`

- [ ] **Step 1: Generate the central sessions migration + copy into tenant migrations**

```bash
php artisan session:table   # creates database/migrations/<ts>_create_sessions_table.php (central)
cp database/migrations/*_create_sessions_table.php \
   database/migrations/tenant/0001_01_01_000100_create_sessions_table.php
php artisan migrate         # central sessions table
```

- [ ] **Step 2: Write the failing test**

```php
<?php // tests/Feature/SessionIsolationTest.php
use App\Tenancy\SessionTenancyBootstrapper;

it('scopes the session cookie name per tenant', function () {
    $org = App\Models\Organization::create(['name' => 'Acme', 'slug' => 'acme']);
    $original = config('session.cookie');

    $bootstrapper = app(SessionTenancyBootstrapper::class);
    $bootstrapper->bootstrap($org);
    expect(config('session.cookie'))->toBe('tenant_'.$org->getTenantKey().'_session');

    $bootstrapper->revert();
    expect(config('session.cookie'))->toBe($original);
});
```

- [ ] **Step 3: Run it — expect failure**

```bash
php artisan test --filter=SessionIsolationTest
```

Expected: FAIL (bootstrapper missing).

- [ ] **Step 4: Implement the bootstrapper**

```php
<?php // app/Tenancy/SessionTenancyBootstrapper.php
namespace App\Tenancy;

use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;

class SessionTenancyBootstrapper implements TenancyBootstrapper
{
    protected ?string $originalCookie = null;

    public function bootstrap(Tenant $tenant): void
    {
        $this->originalCookie = config('session.cookie');
        config(['session.cookie' => 'tenant_'.$tenant->getTenantKey().'_session']);
    }

    public function revert(): void
    {
        if ($this->originalCookie !== null) {
            config(['session.cookie' => $this->originalCookie]);
            $this->originalCookie = null;
        }
    }
}
```

- [ ] **Step 5: Register it** in `config/tenancy.php` `'bootstrappers'` (after `DatabaseTenancyBootstrapper`, so it runs on `TenancyInitialized` before `StartSession`):

```php
'bootstrappers' => [
    Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
    App\Tenancy\SessionTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    // RedisTenancyBootstrapper::class,  // only with phpredis
],
```

- [ ] **Step 6: Run the test**

```bash
php artisan test --filter=SessionIsolationTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat(tenancy): per-tenant session cookie + tenant sessions table"
```

---

## Task 10: Tenant `users` migration

**Files:**
- Create: `database/migrations/tenant/0001_01_01_000000_create_users_table.php`
- (The central `users` table already exists from the starter kit for super-admins.)

- [ ] **Step 1: Add the tenant users migration** (mirrors the starter kit's users shape, lives in the tenant path)

```php
<?php // database/migrations/tenant/0001_01_01_000000_create_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
    }
};
```

- [ ] **Step 2: Verify tenant migration set** (no DB yet — just list)

```bash
ls database/migrations/tenant   # expect: users, password_reset_tokens (this task), sessions (Task 9)
```

- [ ] **Step 3: Commit**

```bash
git add -A && git commit -m "feat(tenancy): tenant users + password_reset_tokens migrations"
```

---

## Task 11: Provisioning — create org → create DB → migrate → seed first user

**Files:**
- Create: `app/Actions/ProvisionOrganization.php`
- Create: `database/seeders/tenant/TenantInitialUserSeeder.php` (or seed inline in the action)
- Modify: `app/Providers/TenancyServiceProvider.php` (confirm `TenantCreated` pipeline: CreateDatabase → MigrateDatabase)
- Test: `tests/Feature/ProvisionOrganizationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/ProvisionOrganizationTest.php
use App\Actions\ProvisionOrganization;
use App\Models\Organization;

it('provisions a tenant database, migrates it, and seeds the first user', function () {
    $org = app(ProvisionOrganization::class)->handle(
        name: 'Acme Co', slug: 'acme',
        adminName: 'Owner', adminEmail: 'owner@acme.test', adminPassword: 'password123',
    );

    expect($org)->toBeInstanceOf(Organization::class);

    // The tenant DB now has a users table with exactly the seeded user.
    tenancy()->initialize($org);
    try {
        $user = App\Models\User::where('email', 'owner@acme.test')->first();
        expect($user)->not->toBeNull()
            ->and(App\Models\User::count())->toBe(1)
            ->and(Illuminate\Support\Facades\Hash::check('password123', $user->password))->toBeTrue();
    } finally {
        tenancy()->end();
    }
});
```

- [ ] **Step 2: Run it — expect failure**

```bash
php artisan test --filter=ProvisionOrganizationTest
```

Expected: FAIL (action missing).

- [ ] **Step 3: Confirm the `TenantCreated` job pipeline** in `app/Providers/TenancyServiceProvider.php` `events()` includes (uncomment if needed):

```php
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners\QueueableListener; // etc.

// Inside events():
Events\TenantCreated::class => [
    JobPipeline::make([
        Jobs\CreateDatabase::class,
        Jobs\MigrateDatabase::class,
        // Jobs\SeedDatabase::class,   // we seed the first user in the action instead
    ])->send(fn (Events\TenantCreated $event) => $event->tenant)
      ->shouldBeQueued(false),   // synchronous for now; flip to true + queue worker in prod
],
Events\TenantDeleted::class => [
    JobPipeline::make([Jobs\DeleteDatabase::class])
      ->send(fn (Events\TenantDeleted $event) => $event->tenant)
      ->shouldBeQueued(false),
],
```

- [ ] **Step 4: Implement the provisioning action**

```php
<?php // app/Actions/ProvisionOrganization.php
namespace App\Actions;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProvisionOrganization
{
    /** Creates the org (fires CreateDatabase+MigrateDatabase), then seeds the first tenant user. */
    public function handle(
        string $name, string $slug,
        string $adminName, string $adminEmail, string $adminPassword,
    ): Organization {
        // Creating the org fires TenantCreated => CreateDatabase => MigrateDatabase (synchronous).
        $org = Organization::create(['name' => $name, 'slug' => $slug]);

        // Seed the initial user inside the tenant context.
        $org->run(function () use ($adminName, $adminEmail, $adminPassword) {
            User::create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
            ]);
        });

        return $org;
    }
}
```

(`$tenant->run(Closure)` initializes tenancy, runs the closure, and reverts — the idiomatic stancl way to seed.)

- [ ] **Step 5: Run the test**

```bash
php artisan test --filter=ProvisionOrganizationTest
```

Expected: PASS. Requires the MySQL user to have `CREATE DATABASE`. If the pipeline didn't create the DB, re-check Step 3 (a cleared/edited `events()` is the usual cause).

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(provisioning): ProvisionOrganization creates DB, migrates, seeds first user"
```

---

## Task 12: Central super-admin area (`/admin`) — login + dashboard

**Files:**
- Modify: `routes/web.php` (central `admin` group on `auth:central`)
- Create: `app/Http/Controllers/Central/AdminSessionController.php`, `app/Http/Controllers/Central/DashboardController.php`
- Create: `resources/js/pages/admin/login.tsx`, `resources/js/pages/admin/dashboard.tsx`
- Create: `database/seeders/DatabaseSeeder.php` super-admin seed
- Test: `tests/Feature/CentralAdminAuthTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/CentralAdminAuthTest.php
use App\Models\CentralUser;
use Illuminate\Support\Facades\Hash;

it('logs a super-admin into the central area and blocks anonymous access', function () {
    CentralUser::create(['name' => 'Root', 'email' => 'root@central.test', 'password' => Hash::make('password123')]);

    $this->get('/admin')->assertRedirect('/admin/login');            // guard: auth:central

    $this->post('/admin/login', ['email' => 'root@central.test', 'password' => 'password123'])
         ->assertRedirect('/admin');

    $this->get('/admin')->assertOk();
});
```

- [ ] **Step 2: Run it — expect failure**

```bash
php artisan test --filter=CentralAdminAuthTest
```

Expected: FAIL.

- [ ] **Step 3: Central routes** in `routes/web.php`

```php
use App\Http\Controllers\Central\AdminSessionController;
use App\Http\Controllers\Central\DashboardController;

Route::prefix('admin')->group(function () {
    Route::middleware('guest:central')->group(function () {
        Route::get('/login', [AdminSessionController::class, 'create'])->name('admin.login');
        Route::post('/login', [AdminSessionController::class, 'store']);
    });
    Route::middleware('auth:central')->group(function () {
        Route::get('/', DashboardController::class)->name('admin.dashboard');
        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('admin.logout');
    });
});
```

- [ ] **Step 4: Session controller** (uses the `central` guard explicitly)

```php
<?php // app/Http/Controllers/Central/AdminSessionController.php
namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminSessionController extends Controller
{
    public function create() { return Inertia::render('admin/login'); }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::guard('central')->attempt($data, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.']);
        }
        $request->session()->regenerate();
        return redirect()->intended('/admin');
    }

    public function destroy(Request $request)
    {
        Auth::guard('central')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/admin/login');
    }
}
```

- [ ] **Step 5: Dashboard controller** (placeholder page)

```php
<?php // app/Http/Controllers/Central/DashboardController.php
namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('admin/dashboard', [
            'organizationsCount' => Organization::count(),
        ]);
    }
}
```

- [ ] **Step 6: Minimal React pages** (polish comes in the UI/UX phase; these just satisfy the flow)

```tsx
// resources/js/pages/admin/login.tsx
import { Form, Head } from '@inertiajs/react';

export default function AdminLogin() {
  return (
    <>
      <Head title="Admin Login" />
      <Form action="/admin/login" method="post" className="mx-auto mt-24 max-w-sm space-y-4">
        {({ errors, processing }) => (
          <>
            <h1 className="text-xl font-semibold">Super-admin sign in</h1>
            <input name="email" type="email" placeholder="Email" className="w-full rounded border p-2" />
            <input name="password" type="password" placeholder="Password" className="w-full rounded border p-2" />
            {errors.email && <p className="text-sm text-red-600">{errors.email}</p>}
            <button disabled={processing} className="w-full rounded bg-black p-2 text-white">Sign in</button>
          </>
        )}
      </Form>
    </>
  );
}
```

```tsx
// resources/js/pages/admin/dashboard.tsx
import { Head } from '@inertiajs/react';

export default function AdminDashboard({ organizationsCount }: { organizationsCount: number }) {
  return (
    <>
      <Head title="Admin" />
      <div className="p-8"><h1 className="text-2xl font-bold">Organizations: {organizationsCount}</h1></div>
    </>
  );
}
```

- [ ] **Step 7: Seed a super-admin** in `database/seeders/DatabaseSeeder.php`

```php
public function run(): void
{
    \App\Models\CentralUser::firstOrCreate(
        ['email' => 'admin@gmail.com'],
        ['name' => 'Super Admin', 'password' => \Illuminate\Support\Facades\Hash::make('password')],
    );
}
```

- [ ] **Step 8: Run the test + a manual smoke**

```bash
php artisan test --filter=CentralAdminAuthTest      # expect PASS
php artisan db:seed                                  # create the super-admin
```

- [ ] **Step 9: Commit**

```bash
git add -A && git commit -m "feat(central): super-admin login + dashboard on central guard"
```

---

## Task 13: Tenant login (`/{slug}/login`) + dashboard + Inertia tenant context

**Files:**
- Modify: `routes/tenant.php` (remove `_probe`; add login + dashboard)
- Create: `app/Http/Controllers/Tenant/TenantSessionController.php`, `app/Http/Controllers/Tenant/DashboardController.php`
- Create: `resources/js/pages/tenant/login.tsx`, `resources/js/pages/tenant/dashboard.tsx`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (share tenant context + flash)
- Test: `tests/Feature/TenantAuthTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/TenantAuthTest.php
use App\Actions\ProvisionOrganization;

beforeEach(function () {
    $this->org = app(ProvisionOrganization::class)->handle(
        'Acme', 'acme', 'Owner', 'owner@acme.test', 'password123',
    );
});

it('logs a tenant user into their own tenant', function () {
    $this->get('/acme/dashboard')->assertRedirect('/acme/login');

    $this->post('/acme/login', ['email' => 'owner@acme.test', 'password' => 'password123'])
         ->assertRedirect('/acme/dashboard');

    $this->get('/acme/dashboard')->assertOk()->assertSee('Acme');
});
```

- [ ] **Step 2: Run it — expect failure**

```bash
php artisan test --filter=TenantAuthTest
```

Expected: FAIL.

- [ ] **Step 3: Replace the probe with real tenant routes** in `routes/tenant.php`

```php
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\TenantSessionController;

Route::middleware(['web', InitializeTenancyByPath::class])
    ->prefix('/{tenant}')
    ->group(function () {
        Route::middleware('guest:web')->group(function () {
            Route::get('/login', [TenantSessionController::class, 'create'])->name('tenant.login');
            Route::post('/login', [TenantSessionController::class, 'store'])->name('tenant.login.store');
        });
        Route::middleware('auth:web')->group(function () {
            Route::get('/dashboard', DashboardController::class)->name('tenant.dashboard');
            Route::post('/logout', [TenantSessionController::class, 'destroy'])->name('tenant.logout');
        });
    });
```

- [ ] **Step 4: Tenant session controller** (default `web` guard = tenant users; note the `{tenant}` slug is needed for redirects)

```php
<?php // app/Http/Controllers/Tenant/TenantSessionController.php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TenantSessionController extends Controller
{
    public function create() { return Inertia::render('tenant/login'); }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::guard('web')->attempt($data, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.']);
        }
        $request->session()->regenerate();
        return redirect()->route('tenant.dashboard', ['tenant' => tenant('slug')]);
    }

    public function destroy(Request $request)
    {
        $slug = tenant('slug');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('tenant.login', ['tenant' => $slug]);
    }
}
```

- [ ] **Step 5: Tenant dashboard controller**

```php
<?php // app/Http/Controllers/Tenant/DashboardController.php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('tenant/dashboard');
    }
}
```

- [ ] **Step 6: Share tenant context + flash** in `app/Http/Middleware/HandleInertiaRequests.php` `share()`

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => ['user' => fn () => $request->user()?->only('id', 'name', 'email')],
        'tenant' => fn () => function_exists('tenant') && tenant()
            ? tenant()->only('id', 'slug', 'name')
            : null,   // null on central/SSR — guard the React side
    ]);
}
```

- [ ] **Step 7: React pages** (read shared `tenant` for links/labels)

```tsx
// resources/js/pages/tenant/login.tsx
import { Form, Head, usePage } from '@inertiajs/react';

export default function TenantLogin() {
  const { tenant } = usePage().props as any;
  return (
    <>
      <Head title={`${tenant?.name ?? 'Tenant'} — Sign in`} />
      <Form action={`/${tenant?.slug}/login`} method="post" className="mx-auto mt-24 max-w-sm space-y-4">
        {({ errors, processing }) => (
          <>
            <h1 className="text-xl font-semibold">{tenant?.name} sign in</h1>
            <input name="email" type="email" placeholder="Email" className="w-full rounded border p-2" />
            <input name="password" type="password" placeholder="Password" className="w-full rounded border p-2" />
            {errors.email && <p className="text-sm text-red-600">{errors.email}</p>}
            <button disabled={processing} className="w-full rounded bg-black p-2 text-white">Sign in</button>
          </>
        )}
      </Form>
    </>
  );
}
```

```tsx
// resources/js/pages/tenant/dashboard.tsx
import { Head, usePage } from '@inertiajs/react';

export default function TenantDashboard() {
  const { tenant, auth } = usePage().props as any;
  return (
    <>
      <Head title={`${tenant?.name} — Dashboard`} />
      <div className="p-8">
        <h1 className="text-2xl font-bold">{tenant?.name}</h1>
        <p className="text-muted-foreground">Signed in as {auth?.user?.name}</p>
      </div>
    </>
  );
}
```

- [ ] **Step 8: Run the test**

```bash
php artisan test --filter=TenantAuthTest
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add -A && git commit -m "feat(tenant): tenant login + dashboard + shared tenant context"
```

---

## Task 14: Impersonation — super-admin logs in as a tenant user

**Files:**
- Publish + migrate impersonation tokens (central)
- Modify: `config/tenancy.php` (enable `UserImpersonation` feature)
- Modify: `routes/tenant.php` (consume route), `routes/web.php` (mint route)
- Create: `app/Http/Controllers/Tenant/ImpersonationController.php`, `app/Http/Controllers/Central/ImpersonateController.php`
- Test: `tests/Feature/ImpersonationTest.php`

- [ ] **Step 1: Enable the feature + migrate the central tokens table**

```bash
php artisan vendor:publish --tag=impersonation-migrations
php artisan migrate
```

In `config/tenancy.php`:

```php
'features' => [
    Stancl\Tenancy\Features\UserImpersonation::class,
    // ... keep existing features
],
```

- [ ] **Step 2: Write the failing test**

```php
<?php // tests/Feature/ImpersonationTest.php
use App\Actions\ProvisionOrganization;
use App\Models\CentralUser;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Features\UserImpersonation;

it('lets a super-admin impersonate a tenant user', function () {
    $org = app(ProvisionOrganization::class)->handle('Acme', 'acme', 'Owner', 'owner@acme.test', 'password123');
    CentralUser::create(['name' => 'Root', 'email' => 'root@central.test', 'password' => Hash::make('password123')]);

    $tenantUserId = $org->run(fn () => App\Models\User::first()->id);

    $token = tenancy()->impersonate($org, $tenantUserId, '/acme/dashboard', 'web');

    $this->get("/acme/impersonate/{$token->token}")->assertRedirect('/acme/dashboard');
    $this->get('/acme/dashboard')->assertOk()->assertSee('Owner');
});
```

- [ ] **Step 3: Run it — expect failure**

```bash
php artisan test --filter=ImpersonationTest
```

Expected: FAIL.

- [ ] **Step 4: Consume route (tenant) — must be a controller, not a closure (route:cache safe)**

```php
// routes/tenant.php — inside the /{tenant} group, OUTSIDE the auth:web group
Route::get('/impersonate/{token}', [\App\Http\Controllers\Tenant\ImpersonationController::class, 'store'])
    ->name('tenant.impersonate');
```

```php
<?php // app/Http/Controllers/Tenant/ImpersonationController.php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Stancl\Tenancy\Features\UserImpersonation;

class ImpersonationController extends Controller
{
    public function store(string $tenant, string $token)
    {
        return UserImpersonation::makeResponse($token);
    }
}
```

- [ ] **Step 5: Mint route (central, super-admin only)**

```php
// routes/web.php — inside the auth:central group
Route::post('/organizations/{organization}/impersonate/{userId}',
    [\App\Http\Controllers\Central\ImpersonateController::class, 'store'])->name('admin.impersonate');
```

```php
<?php // app/Http/Controllers/Central/ImpersonateController.php
namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Organization;

class ImpersonateController extends Controller
{
    public function store(Organization $organization, string $userId)
    {
        $token = tenancy()->impersonate($organization, $userId, '/'.$organization->slug.'/dashboard', 'web');
        return redirect("/{$organization->slug}/impersonate/{$token->token}");
    }
}
```

- [ ] **Step 6: Run the test**

```bash
php artisan test --filter=ImpersonationTest
```

Expected: PASS. If it fails on TTL, the default token TTL is 60s — fine for a redirect; if flaky in CI, set `UserImpersonation::$ttl` higher in a service provider.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat(central): super-admin impersonation of tenant users"
```

---

## Task 15: End-to-end isolation smoke test (two tenants, no cross-auth)

**Files:**
- Test: `tests/Feature/TenantIsolationTest.php`

- [ ] **Step 1: Write the isolation test** (the whole point of the phase)

```php
<?php // tests/Feature/TenantIsolationTest.php
use App\Actions\ProvisionOrganization;

it('keeps two tenants fully isolated in data and auth', function () {
    $acme = app(ProvisionOrganization::class)->handle('Acme', 'acme', 'A', 'a@acme.test', 'password123');
    $globex = app(ProvisionOrganization::class)->handle('Globex', 'globex', 'G', 'g@globex.test', 'password123');

    // Each tenant DB has exactly its own single user.
    expect($acme->run(fn () => App\Models\User::pluck('email')->all()))->toBe(['a@acme.test']);
    expect($globex->run(fn () => App\Models\User::pluck('email')->all()))->toBe(['g@globex.test']);

    // Acme's user cannot log into Globex.
    $this->post('/globex/login', ['email' => 'a@acme.test', 'password' => 'password123'])
         ->assertSessionHasErrors('email');

    // Acme's user logs into Acme and cannot reach Globex's dashboard.
    $this->post('/acme/login', ['email' => 'a@acme.test', 'password' => 'password123'])->assertRedirect('/acme/dashboard');
    $this->get('/acme/dashboard')->assertOk();
    $this->get('/globex/dashboard')->assertRedirect('/globex/login');   // different tenant cookie/session
});
```

- [ ] **Step 2: Run it**

```bash
php artisan test --filter=TenantIsolationTest
```

Expected: PASS. If the last assertion fails (Acme's session authenticates on Globex), the per-tenant session cookie (Task 9) or the DB session driver (Task 3) is misconfigured — revisit those before proceeding.

- [ ] **Step 3: Run the full suite**

```bash
php artisan test
```

Expected: all green.

- [ ] **Step 4: Manual browser smoke** (optional but recommended)

```bash
composer run dev   # serves app + Vite + queue + logs
# 1. Visit /admin/login, sign in as admin@gmail.com / password
# 2. (Temporary) tinker-provision an org, or add a create-org form in Phase 1 follow-up:
php artisan tinker --execute="app(App\Actions\ProvisionOrganization::class)->handle('Demo','demo','Demo Owner','owner@demo.test','password');"
# 3. Visit /demo/login, sign in as owner@demo.test / password -> lands on /demo/dashboard
```

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "test(tenancy): end-to-end two-tenant isolation smoke"
```

---

## Self-review (author checklist — completed)

**Spec coverage vs `2026-07-04-...-design.md` §4 (Foundation):**
- Two-DB / two-user-world → Tasks 4, 6, 10, 12, 13 ✅
- Path identification by slug → Tasks 5, 7 ✅
- Auth isolation (central vs tenant, sessions) → Tasks 6, 8, 9, 13, 15 ✅
- Provisioning (create org → DB → migrate → seed first user) → Task 11 ✅
- Reserved slugs → Task 7 ✅
- Impersonation → Task 14 ✅
- Scaffold via `laravel new` React kit (Laravel 13 + Inertia v3) → Task 1 ✅
- `organization_id` dropped on tenant tables → N/A here (business tables land in Phase 2, and by design carry no org column) ✅

**Deferred to later phases (intentionally out of Phase 1 scope):** business tables + services (Phase 2–5), tenant `/{slug}/users` management UI (Phase 2), the polished app shell / dashboards (Phase 6), roles (out of scope per spec).

**Placeholder scan:** no TBD/TODO; every code step shows real code; every command has an expected result. ✅

**Type/name consistency:** `Organization`, `CentralUser`, `User`, `PathSlugTenantResolver`, `SessionTenancyBootstrapper`, `ProvisionOrganization`, guards `web`/`central`, providers `tenant_users`/`central_users` are used consistently across Tasks 4–15. ✅

**Empirical-verification steps** (from research "biggest unknowns") are baked in where they matter: composer resolution (Task 2), resolver signature (Task 5 note), middleware priority (Task 8), session isolation (Tasks 9 & 15).

## Notes for the implementer
- The stack is newer than most training data. When an API detail differs from this plan (a stancl class signature, an Inertia v3 export, a Fortify config key), **check `vendor/` source or the live docs** listed in the spec's research rather than guessing — and fix the plan inline.
- Keep central vs tenant migrations straight: `database/migrations/` (central) vs `database/migrations/tenant/` (per-tenant). This is the most common early mistake.
- `composer run dev` is the dev loop (server + queue + Vite + logs); `npm run build` regenerates Wayfinder typed routes.
