# Tenant Soft Delete & Force Delete Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let central admins soft-delete a tenant (data + database retained, tenant URLs become inaccessible), manage soft-deleted tenants on a dedicated Archived page (restore or permanently force-delete, which drops the tenant database), and move the tenants list off the dashboard onto its own `/admin/tenants` page.

**Architecture:** `App\Models\Tenant` already uses `SoftDeletes`, but stancl wires `TenantDeleted → DeleteDatabase` on Eloquent's `deleted` event — which soft delete also fires. We remap the model's `$dispatchesEvents` so DB teardown fires on `forceDeleted` instead. The path resolver already honours the `SoftDeletes` scope (cache off), so trashed tenants 404 with no extra code. Backend work is split across the central `DashboardController` (stats only) and `TenantController` (list/create/soft-delete/trash/restore/force-delete); frontend adds `admin/tenants/index.tsx` and `admin/tenants/trashed.tsx`.

**Tech Stack:** Laravel 13, stancl/tenancy v3 (multi-DB), Inertia v3 + React 19 + TypeScript, Tailwind v4, shadcn/ui (`Dialog`), Pest. String route paths (no Ziggy). Package manager: Bun.

**Spec:** `docs/superpowers/specs/2026-07-05-tenant-soft-delete-and-force-delete-design.md`

**Conventions:**
- PHP: run `vendor/bin/pint --dirty` before each commit.
- Frontend: run `bun run check` then `bun run types:check` before each commit that touches TS/TSX.
- Tests: `php artisan test --compact` (or `--filter=Name`).
- Confirmations use the existing `Dialog` primitive (see `resources/js/pages/tenant/categories/index.tsx`), NOT `alert-dialog`.
- Central admin auth in tests: `CentralUser::create([...])` + `->actingAs($admin, 'central')`.
- Real tenant DBs in tests: `app(ProvisionTenant::class)->handle($name,$slug,$adminName,$adminEmail,$adminPassword)`. `Tests\TestCase` auto-drops `msai_test_tenant_*` databases in `tearDown`.

---

## Task 1: Move tenant DB teardown from soft delete to force delete

Neutralises the footgun: soft delete must keep the tenant database; only force delete drops it. Tested directly at the model level.

**Files:**
- Modify: `app/Models/Tenant.php`
- Test: `tests/Feature/Central/TenantDatabaseTeardownTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Central/TenantDatabaseTeardownTest.php`:

```php
<?php

use App\Actions\ProvisionTenant;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

function tenantDatabaseExists(string $slug): bool
{
    $name = config('tenancy.database.prefix').$slug;

    return DB::connection('central')->select('SHOW DATABASES LIKE ?', [$name]) !== [];
}

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('keeps the tenant database when the tenant is soft deleted', function () {
    expect(tenantDatabaseExists('acme'))->toBeTrue();

    $this->tenant->delete();

    expect($this->tenant->trashed())->toBeTrue()
        ->and(tenantDatabaseExists('acme'))->toBeTrue();
});

it('drops the tenant database when the tenant is force deleted', function () {
    $this->tenant->forceDelete();

    expect(Tenant::withTrashed()->find('acme'))->toBeNull()
        ->and(tenantDatabaseExists('acme'))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify the soft-delete case fails**

Run: `php artisan test --filter=TenantDatabaseTeardownTest`
Expected: the "keeps the tenant database" test FAILS — currently soft delete fires `deleted → TenantDeleted → DeleteDatabase`, so the database is dropped and `tenantDatabaseExists('acme')` is `false`.

- [ ] **Step 3: Remap the delete events in the model**

In `app/Models/Tenant.php`, add the `Events` import and a `$dispatchesEvents` override. Add to the `use` block at the top:

```php
use Stancl\Tenancy\Events;
```

Then add this property inside the class (e.g. directly after the `$keyType` property):

```php
/**
 * Remap of stancl's base $dispatchesEvents: DB teardown (TenantDeleted ->
 * DeleteDatabase job, wired in TenancyServiceProvider) must fire on a FORCE
 * delete, not on the soft-delete `deleted` event. Otherwise soft-deleting a
 * tenant would drop its database and make restore impossible.
 *
 * @var array<string, class-string>
 */
protected $dispatchesEvents = [
    'saving' => Events\SavingTenant::class,
    'saved' => Events\TenantSaved::class,
    'creating' => Events\CreatingTenant::class,
    'created' => Events\TenantCreated::class,
    'updating' => Events\UpdatingTenant::class,
    'updated' => Events\TenantUpdated::class,
    'deleting' => Events\DeletingTenant::class,
    // 'deleted' intentionally NOT mapped to TenantDeleted (see docblock).
    'forceDeleted' => Events\TenantDeleted::class,
];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TenantDatabaseTeardownTest`
Expected: PASS (both cases). Soft delete keeps `msai_test_tenant_acme`; force delete drops it.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/Tenant.php tests/Feature/Central/TenantDatabaseTeardownTest.php
git commit -m "feat(tenancy): drop tenant DB only on force delete, not soft delete"
```

---

## Task 2: Slim the dashboard controller to stats-only

Remove the tenants list from the dashboard; it moves to `/admin/tenants` in Task 3.

**Files:**
- Modify: `app/Http/Controllers/Central/DashboardController.php`
- Modify: `tests/Pest.php` (add shared `makeTenants` helper)
- Modify: `tests/Feature/Central/AdminDashboardTest.php` (retarget to stats-only)

- [ ] **Step 1: Move the `makeTenants` helper into `tests/Pest.php`**

In `tests/Pest.php`, replace the stub `something()` function with:

```php
/**
 * Insert tenant rows WITHOUT firing TenantCreated, so list/stat tests don't
 * provision (and later drop) a real database per row.
 */
function makeTenants(int $count): void
{
    \App\Models\Tenant::withoutEvents(function () use ($count) {
        foreach (range(1, $count) as $i) {
            \App\Models\Tenant::create(['name' => "Company {$i}", 'id' => "co-{$i}"]);
        }
    });
}
```

- [ ] **Step 2: Rewrite `AdminDashboardTest.php` to assert stats-only**

Replace the entire contents of `tests/Feature/Central/AdminDashboardTest.php` with:

```php
<?php

use App\Models\CentralUser;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'password',
    ]);
});

it('renders the dashboard with stats and no tenants list', function () {
    makeTenants(3);

    $this->actingAs($this->admin, 'central')
        ->get('/admin/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('stats.total', 3)
            ->missing('tenants')
        );
});

it('redirects a guest away from the dashboard', function () {
    $this->get('/admin/dashboard')->assertRedirect('/admin/login');
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --filter=AdminDashboardTest`
Expected: FAIL — the dashboard still sends a `tenants` prop, so `->missing('tenants')` fails.

- [ ] **Step 4: Slim `DashboardController` to stats-only**

Replace the entire contents of `app/Http/Controllers/Central/DashboardController.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => $this->stats(),
        ]);
    }

    /**
     * Aggregate stats over ALL (non-trashed) tenants. Counts use the app
     * timezone (UTC).
     *
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $newest = Tenant::query()->latest()->first(['name', 'created_at']);

        return [
            'total' => Tenant::query()->count(),
            'added_today' => Tenant::query()->whereDate('created_at', today())->count(),
            'last_7_days' => Tenant::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'newest' => $newest ? [
                'name' => $newest->name,
                'created_at' => $newest->created_at,
            ] : null,
        ];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=AdminDashboardTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Central/DashboardController.php tests/Pest.php tests/Feature/Central/AdminDashboardTest.php
git commit -m "refactor(admin): dashboard controller returns stats only"
```

---

## Task 3: Tenants index route + controller method (moved list)

Add `GET /admin/tenants` serving the paginated/searchable list previously on the dashboard.

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Central/TenantController.php`
- Test: `tests/Feature/Central/TenantIndexTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Central/TenantIndexTest.php`:

```php
<?php

use App\Models\CentralUser;
use App\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'password',
    ]);
});

it('paginates the tenants list and exposes per-page', function () {
    makeTenants(12);

    $this->actingAs($this->admin, 'central')
        ->get('/admin/tenants?per_page=10')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/tenants/index')
            ->has('tenants.data', 10)
            ->where('tenants.total', 12)
            ->where('tenants.per_page', 10)
            ->where('tenants.last_page', 2)
            ->where('filters.per_page', 10)
        );
});

it('returns the requested page', function () {
    makeTenants(12);

    $this->actingAs($this->admin, 'central')
        ->get('/admin/tenants?per_page=10&page=2')
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 2)
            ->where('tenants.current_page', 2)
        );
});

it('filters the paginated tenants by search (name or slug)', function () {
    Tenant::withoutEvents(function () {
        Tenant::create(['name' => 'Acme Manufacturing', 'id' => 'acme']);
        Tenant::create(['name' => 'Globex', 'id' => 'globex']);
    });

    $this->actingAs($this->admin, 'central')
        ->get('/admin/tenants?search=acme')
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 1)
            ->where('tenants.data.0.slug', 'acme')
            ->where('tenants.total', 1)
            ->where('filters.search', 'acme')
        );
});

it('clamps an out-of-range per_page back to the default', function () {
    makeTenants(1);

    $this->actingAs($this->admin, 'central')
        ->get('/admin/tenants?per_page=999')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenants.per_page', 10)
            ->where('filters.per_page', 10)
        );
});

it('redirects a guest away from the tenants index', function () {
    $this->get('/admin/tenants')->assertRedirect('/admin/login');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TenantIndexTest`
Expected: FAIL — route `/admin/tenants` (GET) does not exist yet (404 / redirect mismatch).

- [ ] **Step 3: Add the index route**

In `routes/web.php`, inside the `Route::middleware('auth:central')->group(...)` block, add the GET route immediately above the existing `tenants.store` line:

```php
Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
```

- [ ] **Step 4: Add the `index` method to `TenantController`**

In `app/Http/Controllers/Central/TenantController.php`, add the imports and the method. Update the top of the file so the `use` block includes:

```php
use App\Models\Tenant;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
```

Add a class constant and the `index` method (keep the existing `store` method):

```php
/** @var array<int, int> */
private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

public function index(Request $request): Response
{
    $search = trim((string) $request->string('search'));

    $perPage = (int) $request->integer('per_page', 10);
    if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
        $perPage = 10;
    }

    $tenants = Tenant::query()
        ->when($search !== '', function (Builder $query) use ($search): void {
            $query->where(function (Builder $group) use ($search): void {
                $group->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        })
        ->latest()
        ->paginate($perPage)
        ->withQueryString()
        ->through(fn (Tenant $tenant): array => [
            'slug' => $tenant->getKey(),
            'name' => $tenant->name,
            'created_at' => $tenant->created_at,
        ]);

    return Inertia::render('admin/tenants/index', [
        'tenants' => $tenants,
        'filters' => [
            'search' => $search,
            'per_page' => $perPage,
        ],
    ]);
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TenantIndexTest`
Expected: PASS (all 5 cases).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add routes/web.php app/Http/Controllers/Central/TenantController.php tests/Feature/Central/TenantIndexTest.php
git commit -m "feat(admin): add /admin/tenants list endpoint (moved off dashboard)"
```

---

## Task 4: Soft delete, trashed list, restore, force delete (routes + controller + tests)

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Central/TenantController.php`
- Modify: `app/Http/Requests/Central/StoreTenantRequest.php`
- Test: `tests/Feature/Central/TenantLifecycleTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Central/TenantLifecycleTest.php`:

```php
<?php

use App\Actions\ProvisionTenant;
use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

function tenantDbExists(string $slug): bool
{
    $name = config('tenancy.database.prefix').$slug;

    return DB::connection('central')->select('SHOW DATABASES LIKE ?', [$name]) !== [];
}

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Root',
        'email' => 'root@example.com',
        'password' => 'password',
    ]);

    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('soft deletes a tenant and keeps its database', function () {
    $this->actingAs($this->admin, 'central')
        ->from('/admin/tenants')
        ->delete('/admin/tenants/acme')
        ->assertRedirect('/admin/tenants')
        ->assertSessionHas('success');

    expect(Tenant::withTrashed()->find('acme')->trashed())->toBeTrue()
        ->and(tenantDbExists('acme'))->toBeTrue();
});

it('makes a soft-deleted tenant inaccessible', function () {
    $this->tenant->delete();

    $this->get('/acme/login')->assertNotFound();
});

it('lists only soft-deleted tenants on the archived page', function () {
    $this->tenant->delete();

    $this->actingAs($this->admin, 'central')
        ->get('/admin/tenants/trashed')
        ->assertInertia(fn ($page) => $page
            ->component('admin/tenants/trashed')
            ->has('tenants.data', 1)
            ->where('tenants.data.0.slug', 'acme')
        );
});

it('restores a soft-deleted tenant and makes it reachable again', function () {
    $this->tenant->delete();

    $this->actingAs($this->admin, 'central')
        ->from('/admin/tenants/trashed')
        ->patch('/admin/tenants/acme/restore')
        ->assertRedirect('/admin/tenants/trashed')
        ->assertSessionHas('success');

    expect(Tenant::find('acme'))->not->toBeNull();

    $this->get('/acme/login')->assertOk();
});

it('force deletes a trashed tenant and drops its database', function () {
    $this->tenant->delete();

    $this->actingAs($this->admin, 'central')
        ->from('/admin/tenants/trashed')
        ->delete('/admin/tenants/acme/force')
        ->assertRedirect('/admin/tenants/trashed')
        ->assertSessionHas('success');

    expect(Tenant::withTrashed()->find('acme'))->toBeNull()
        ->and(tenantDbExists('acme'))->toBeFalse();
});

it('rejects lifecycle routes for guests', function () {
    $this->delete('/admin/tenants/acme')->assertRedirect('/admin/login');
    $this->get('/admin/tenants/trashed')->assertRedirect('/admin/login');
    $this->patch('/admin/tenants/acme/restore')->assertRedirect('/admin/login');
    $this->delete('/admin/tenants/acme/force')->assertRedirect('/admin/login');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TenantLifecycleTest`
Expected: FAIL — the `destroy`/`trashed`/`restore`/`force` routes don't exist yet.

- [ ] **Step 3: Add the lifecycle routes**

In `routes/web.php`, inside the `auth:central` group, below the `tenants.store` line, add:

```php
Route::get('tenants/trashed', [TenantController::class, 'trashed'])->name('tenants.trashed');
Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');
Route::patch('tenants/{tenant}/restore', [TenantController::class, 'restore'])
    ->withTrashed()
    ->name('tenants.restore');
Route::delete('tenants/{tenant}/force', [TenantController::class, 'forceDestroy'])
    ->withTrashed()
    ->name('tenants.force-destroy');
```

Note: `tenants/trashed` is declared before `tenants/{tenant}` routes; it can't collide anyway (GET vs DELETE/PATCH), but keep it first for clarity.

- [ ] **Step 4: Add the controller methods**

In `app/Http/Controllers/Central/TenantController.php`, add `use Illuminate\Http\RedirectResponse;` to the imports if not already present (it is — `store` uses it). Add these methods (keep `index` and `store`):

```php
public function trashed(Request $request): Response
{
    $search = trim((string) $request->string('search'));

    $perPage = (int) $request->integer('per_page', 10);
    if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
        $perPage = 10;
    }

    $tenants = Tenant::onlyTrashed()
        ->when($search !== '', function (Builder $query) use ($search): void {
            $query->where(function (Builder $group) use ($search): void {
                $group->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        })
        ->orderByDesc('deleted_at')
        ->paginate($perPage)
        ->withQueryString()
        ->through(fn (Tenant $tenant): array => [
            'slug' => $tenant->getKey(),
            'name' => $tenant->name,
            'deleted_at' => $tenant->deleted_at,
        ]);

    return Inertia::render('admin/tenants/trashed', [
        'tenants' => $tenants,
        'filters' => [
            'search' => $search,
            'per_page' => $perPage,
        ],
    ]);
}

public function destroy(Tenant $tenant): RedirectResponse
{
    $tenant->delete();

    return back()->with('success', "Tenant \"{$tenant->name}\" moved to the archive.");
}

public function restore(Tenant $tenant): RedirectResponse
{
    $tenant->restore();

    return back()->with('success', "Tenant \"{$tenant->name}\" restored.");
}

public function forceDestroy(Tenant $tenant): RedirectResponse
{
    $name = $tenant->name;
    $tenant->forceDelete();

    return back()->with('success', "Tenant \"{$name}\" permanently deleted.");
}
```

- [ ] **Step 5: Sharpen the duplicate-slug message**

In `app/Http/Requests/Central/StoreTenantRequest.php`, update the `slug.unique` message in the `messages()` array:

```php
'slug.unique' => 'A tenant with that slug already exists (it may be in the archive — restore or permanently delete it first).',
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=TenantLifecycleTest`
Expected: PASS (all 6 cases).

- [ ] **Step 7: Run the full central suite**

Run: `php artisan test --filter=Central`
Expected: PASS — Task 1–4 tests all green.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty
git add routes/web.php app/Http/Controllers/Central/TenantController.php app/Http/Requests/Central/StoreTenantRequest.php tests/Feature/Central/TenantLifecycleTest.php
git commit -m "feat(admin): soft delete, archive list, restore, and force delete tenants"
```

---

## Task 5: Frontend — tenants index page (moved list + soft-delete action)

Create `admin/tenants/index.tsx` from the current dashboard's list UI, and add the soft-delete `Dialog`.

**Files:**
- Create: `resources/js/pages/admin/tenants/index.tsx`

- [ ] **Step 1: Copy the dashboard as the starting point**

```bash
mkdir -p resources/js/pages/admin/tenants
cp resources/js/pages/admin/dashboard.tsx resources/js/pages/admin/tenants/index.tsx
```

- [ ] **Step 2: Transform `index.tsx` into the tenants list page**

Edit `resources/js/pages/admin/tenants/index.tsx` with these exact changes:

1. Rename the default export function `AdminDashboard` → `AdminTenantsIndex`.
2. Remove the stats feature: delete the `Stats` type, the `stats` destructured from `usePage`, the `StatCard` component, the `greeting`/`firstName` logic and its `useEffect`, and the entire stats-cards `<div className="grid ...">…</div>` block plus the `stats.total === 0` empty-state branch. Keep the tenants `Card` (search + table + pagination) and the create `Sheet`.
3. `PageProps`: drop `stats`. It becomes:

```tsx
type PageProps = {
    tenants: Paginator<Tenant>;
    filters: { search: string; per_page: number };
    flash: { success: string | null };
};
```

4. Point every list request at `/admin/tenants` (was `/admin/dashboard`): the debounced-search `router.get('/admin/dashboard', …)` and the per-page `visit('/admin/dashboard', …)` calls both become `/admin/tenants`.
5. Change the `listReload` `only` array from `['tenants', 'filters']` — it is already correct; ensure it does NOT reference `stats`.
6. Replace the page header block (the old `<h1>Dashboard</h1>` + greeting) with a title + Archived link:

```tsx
<div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div className="flex flex-col gap-1">
        <h1 className="font-semibold text-2xl tracking-tight">Tenants</h1>
        <p className="text-muted-foreground text-sm">
            Provision, search, and manage every tenant workspace.
        </p>
    </div>
    <Button asChild variant="outline">
        <Link href="/admin/tenants/trashed">
            <Archive className="size-4" />
            Archived
        </Link>
    </Button>
</div>
```

7. Add these imports: `Link` from `@inertiajs/react` (extend the existing import), and `Archive` + `Trash2` from `lucide-react` (extend the existing import). Add `DropdownMenuSeparator` to the existing `@/components/ui/dropdown-menu` import, and `Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle` from `@/components/ui/dialog`.
8. Add soft-delete state near the other `useState` hooks:

```tsx
const [deleting, setDeleting] = useState<Tenant | null>(null);
```

9. Add the delete handler near the other handlers (e.g. after `handleCopy`):

```tsx
const confirmDelete = () => {
    if (!deleting) {
        return;
    }

    router.delete(`/admin/tenants/${deleting.slug}`, {
        preserveScroll: true,
        onSuccess: (page) => {
            setDeleting(null);
            const message = (page.props as unknown as PageProps).flash
                ?.success;
            if (message) {
                toast.success(message);
            }
        },
    });
};
```

10. In the row `DropdownMenuContent`, append a separator + destructive Delete item after the existing "Copy slug" item:

```tsx
<DropdownMenuSeparator />
<DropdownMenuItem
    variant="destructive"
    onSelect={() => setDeleting(tenant)}
>
    <Trash2 className="size-4" />
    Delete tenant
</DropdownMenuItem>
```

11. Add the confirmation `Dialog` just before the closing `</CentralAdminLayout>` (mirrors the categories delete dialog):

```tsx
<Dialog
    open={deleting !== null}
    onOpenChange={(next) => {
        if (!next) {
            setDeleting(null);
        }
    }}
>
    <DialogContent>
        <DialogHeader>
            <DialogTitle>Delete tenant</DialogTitle>
            <DialogDescription>
                Move “{deleting?.name}” to the archive? Its workspace
                (/{deleting?.slug}) becomes inaccessible, but its data and
                database are kept — you can restore it from Archived.
            </DialogDescription>
        </DialogHeader>
        <DialogFooter>
            <Button
                type="button"
                variant="ghost"
                onClick={() => setDeleting(null)}
            >
                Cancel
            </Button>
            <Button
                type="button"
                variant="destructive"
                onClick={confirmDelete}
            >
                <Trash2 className="size-4" />
                Delete
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
```

12. Update the `<Head title="Admin" />` to `<Head title="Tenants" />`.

- [ ] **Step 3: Verify the frontend compiles**

Run: `bun run check && bun run types:check`
Expected: 0 errors. (Biome may reformat; that's fine.)

- [ ] **Step 4: Build to confirm no runtime import errors**

Run: `bun run build`
Expected: build succeeds.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/admin/tenants/index.tsx
git commit -m "feat(admin): tenants index page with soft-delete action"
```

---

## Task 6: Frontend — slim the dashboard + sidebar nav

**Files:**
- Modify: `resources/js/pages/admin/dashboard.tsx`
- Modify: `resources/js/components/admin/admin-sidebar.tsx`

- [ ] **Step 1: Slim `dashboard.tsx` to stats + CTA**

Replace the entire contents of `resources/js/pages/admin/dashboard.tsx` with:

```tsx
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    CalendarPlus,
    CalendarRange,
    Clock,
} from 'lucide-react';
import { type ComponentType, type ReactNode, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import { cn } from '@/lib/utils';

type Stats = {
    total: number;
    added_today: number;
    last_7_days: number;
    newest: { name: string; created_at: string } | null;
};

type PageProps = {
    auth: { user: { name: string; email: string } | null };
    stats: Stats;
};

const RELATIVE_TIME = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

/** Best-effort relative timestamp; render-time snapshot (does not tick live). */
function timeAgo(iso: string): string {
    const then = new Date(iso).getTime();

    if (Number.isNaN(then)) {
        return '';
    }

    const seconds = Math.round((then - Date.now()) / 1000);
    const abs = Math.abs(seconds);

    if (abs < 60) {
        return 'just now';
    }

    const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['minute', 60],
        ['hour', 3600],
        ['day', 86400],
        ['month', 2592000],
        ['year', 31536000],
    ];

    let chosen: [Intl.RelativeTimeFormatUnit, number] = ['minute', 60];

    for (const unit of units) {
        if (abs >= unit[1]) {
            chosen = unit;
        }
    }

    return RELATIVE_TIME.format(Math.round(seconds / chosen[1]), chosen[0]);
}

function StatCard({
    icon: Icon,
    label,
    value,
    sub,
    valueClassName,
}: {
    icon: ComponentType<{ className?: string }>;
    label: string;
    value: ReactNode;
    sub: ReactNode;
    valueClassName?: string;
}) {
    return (
        <Card className="p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <p
                        className={cn(
                            'font-semibold text-2xl tabular-nums',
                            valueClassName,
                        )}
                    >
                        {value}
                    </p>
                    <p className="truncate text-muted-foreground text-xs">
                        {sub}
                    </p>
                </div>
                <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-secondary text-foreground">
                    <Icon className="size-4" />
                </span>
            </div>
        </Card>
    );
}

export default function AdminDashboard() {
    const { auth, stats } = usePage().props as unknown as PageProps;
    const [greeting, setGreeting] = useState('Welcome back');

    const firstName = auth.user?.name?.trim().split(/\s+/)[0] || 'Admin';

    // Time-of-day greeting computed after mount to avoid SSR/timezone mismatch.
    useEffect(() => {
        const hour = new Date().getHours();
        setGreeting(
            hour < 12
                ? 'Good morning'
                : hour < 18
                  ? 'Good afternoon'
                  : 'Good evening',
        );
    }, []);

    return (
        <CentralAdminLayout>
            <Head title="Admin" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        Dashboard
                    </h1>
                    <p
                        className="text-muted-foreground text-sm"
                        suppressHydrationWarning
                    >
                        {greeting}, {firstName}. Here's an overview of every
                        tenant workspace.
                    </p>
                </div>
                <Button asChild>
                    <Link href="/admin/tenants">
                        Manage tenants
                        <ArrowRight className="size-4" />
                    </Link>
                </Button>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    icon={Building2}
                    label="Total tenants"
                    value={stats.total}
                    sub="workspaces provisioned"
                />
                <StatCard
                    icon={CalendarPlus}
                    label="Added today"
                    value={stats.added_today}
                    sub="provisioned today (UTC)"
                />
                <StatCard
                    icon={CalendarRange}
                    label="Last 7 days"
                    value={stats.last_7_days}
                    sub="newly provisioned"
                />
                <StatCard
                    icon={Clock}
                    label="Newest tenant"
                    value={stats.newest?.name ?? '—'}
                    valueClassName="truncate text-base font-medium"
                    sub={
                        stats.newest ? (
                            <span suppressHydrationWarning>
                                {timeAgo(stats.newest.created_at)}
                            </span>
                        ) : (
                            'No tenants yet'
                        )
                    }
                />
            </div>
        </CentralAdminLayout>
    );
}
```

- [ ] **Step 2: Add sidebar nav items**

Replace the `mainNavItems` array and imports in `resources/js/components/admin/admin-sidebar.tsx`. Change the lucide import line to:

```tsx
import { Archive, Building2, LayoutGrid } from 'lucide-react';
```

Replace `mainNavItems` with:

```tsx
const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Tenants',
        href: '/admin/tenants',
        icon: Building2,
    },
    {
        title: 'Archived',
        href: '/admin/tenants/trashed',
        icon: Archive,
    },
];
```

- [ ] **Step 3: Verify the frontend compiles**

Run: `bun run check && bun run types:check`
Expected: 0 errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/admin/dashboard.tsx resources/js/components/admin/admin-sidebar.tsx
git commit -m "feat(admin): stats-only dashboard + Tenants/Archived sidebar nav"
```

---

## Task 7: Frontend — Archived page (restore + force delete with type-to-confirm)

**Files:**
- Create: `resources/js/pages/admin/tenants/trashed.tsx`

- [ ] **Step 1: Create the Archived page**

Create `resources/js/pages/admin/tenants/trashed.tsx` with the full contents below. It mirrors the index page's table/search/pagination shell, with `deleted_at` instead of `created_at`, a Restore dialog, and a type-to-confirm force-delete dialog.

```tsx
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArchiveX,
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    LoaderCircle,
    MoreHorizontal,
    RotateCcw,
    Search,
    SearchX,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import { cn } from '@/lib/utils';

type TrashedTenant = {
    name: string;
    slug: string;
    deleted_at: string;
};

type Paginator<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type PageProps = {
    tenants: Paginator<TrashedTenant>;
    filters: { search: string; per_page: number };
    flash: { success: string | null };
};

const PER_PAGE_OPTIONS = [10, 25, 50, 100];
const RELATIVE_TIME = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

const listReload = (onStart: () => void, onFinish: () => void) => ({
    only: ['tenants', 'filters'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
    onStart,
    onFinish,
});

function timeAgo(iso: string): string {
    const then = new Date(iso).getTime();

    if (Number.isNaN(then)) {
        return '';
    }

    const seconds = Math.round((then - Date.now()) / 1000);
    const abs = Math.abs(seconds);

    if (abs < 60) {
        return 'just now';
    }

    const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['minute', 60],
        ['hour', 3600],
        ['day', 86400],
        ['month', 2592000],
        ['year', 31536000],
    ];

    let chosen: [Intl.RelativeTimeFormatUnit, number] = ['minute', 60];

    for (const unit of units) {
        if (abs >= unit[1]) {
            chosen = unit;
        }
    }

    return RELATIVE_TIME.format(Math.round(seconds / chosen[1]), chosen[0]);
}

function flashToast(page: { props: unknown }): void {
    const message = (page.props as PageProps).flash?.success;
    if (message) {
        toast.success(message);
    }
}

export default function AdminTenantsTrashed() {
    const { tenants, filters } = usePage().props as unknown as PageProps;
    const getInitials = useInitials();

    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [restoring, setRestoring] = useState<TrashedTenant | null>(null);
    const [purging, setPurging] = useState<TrashedTenant | null>(null);
    const [confirmText, setConfirmText] = useState('');
    const searchRef = useRef<HTMLInputElement>(null);

    // Debounced server-side search (trimmed guard mirrors the index page).
    useEffect(() => {
        const q = search.trim();

        if (q === filters.search) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.get(
                '/admin/tenants/trashed',
                { search: q || undefined, per_page: filters.per_page },
                listReload(
                    () => setLoading(true),
                    () => setLoading(false),
                ),
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search, filters.search, filters.per_page]);

    const visit = (
        url: string | null,
        data: Record<string, string | number | undefined> = {},
    ) => {
        if (url === null) {
            return;
        }

        router.get(
            url,
            data,
            listReload(
                () => setLoading(true),
                () => setLoading(false),
            ),
        );
    };

    const clearSearch = () => {
        setSearch('');
        searchRef.current?.focus();
    };

    const confirmRestore = () => {
        if (!restoring) {
            return;
        }

        router.patch(
            `/admin/tenants/${restoring.slug}/restore`,
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setRestoring(null);
                    flashToast(page);
                },
            },
        );
    };

    const closePurge = () => {
        setPurging(null);
        setConfirmText('');
    };

    const confirmPurge = () => {
        if (!purging || confirmText !== purging.slug) {
            return;
        }

        router.delete(`/admin/tenants/${purging.slug}/force`, {
            preserveScroll: true,
            onSuccess: (page) => {
                closePurge();
                flashToast(page);
            },
        });
    };

    return (
        <CentralAdminLayout>
            <Head title="Archived tenants" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        Archived tenants
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Soft-deleted workspaces. Restore one, or permanently
                        delete it to drop its database for good.
                    </p>
                </div>
                <Button asChild variant="outline">
                    <Link href="/admin/tenants">
                        <ArrowLeft className="size-4" />
                        Back to tenants
                    </Link>
                </Button>
            </div>

            {tenants.total === 0 && filters.search === '' ? (
                <Card>
                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                        <span className="grid size-12 place-items-center rounded-xl bg-secondary text-foreground">
                            <ArchiveX className="size-6" />
                        </span>
                        <div className="space-y-1">
                            <h3 className="font-semibold text-lg">
                                Archive is empty
                            </h3>
                            <p className="mx-auto max-w-sm text-muted-foreground text-sm">
                                Deleted tenants show up here. You can restore
                                them or permanently remove them.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardHeader className="gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <CardTitle>Archived</CardTitle>
                                <Badge
                                    variant="secondary"
                                    className="tabular-nums"
                                >
                                    {tenants.total}
                                </Badge>
                            </div>
                            <div className="relative w-full sm:w-64">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    ref={searchRef}
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search name or slug…"
                                    aria-label="Search archived tenants"
                                    className="px-9"
                                />
                                {loading ? (
                                    <LoaderCircle className="absolute top-1/2 right-2.5 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                                ) : (
                                    search !== '' && (
                                        <button
                                            type="button"
                                            onClick={clearSearch}
                                            aria-label="Clear search"
                                            className="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    )
                                )}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <p role="status" aria-live="polite" className="sr-only">
                            {tenants.data.length > 0
                                ? `Showing ${tenants.from} to ${tenants.to} of ${tenants.total} archived tenants`
                                : `No archived tenants match "${filters.search}"`}
                        </p>
                        <div
                            aria-busy={loading}
                            className={cn(
                                'overflow-x-auto transition-opacity',
                                loading && 'pointer-events-none opacity-60',
                            )}
                        >
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-border border-b">
                                        <th className="h-10 px-4 text-left font-medium text-muted-foreground text-xs uppercase tracking-wide">
                                            Tenant
                                        </th>
                                        <th className="hidden h-10 px-4 text-left font-medium text-muted-foreground text-xs uppercase tracking-wide sm:table-cell">
                                            Slug
                                        </th>
                                        <th className="hidden h-10 px-4 text-left font-medium text-muted-foreground text-xs uppercase tracking-wide md:table-cell">
                                            Deleted
                                        </th>
                                        <th className="h-10 px-4 text-right">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tenants.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={4}>
                                                <div className="flex flex-col items-center gap-2 py-12 text-center">
                                                    <SearchX className="size-6 text-muted-foreground" />
                                                    <p className="text-muted-foreground text-sm">
                                                        No archived tenants match
                                                        “{filters.search}”
                                                    </p>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={clearSearch}
                                                    >
                                                        Clear search
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        tenants.data.map((tenant) => (
                                            <tr
                                                key={tenant.slug}
                                                className="border-border border-b transition-colors last:border-0 hover:bg-muted/50"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-muted font-medium text-xs">
                                                            {getInitials(
                                                                tenant.name,
                                                            )}
                                                        </span>
                                                        <div className="min-w-0">
                                                            <p className="truncate font-medium text-foreground">
                                                                {tenant.name}
                                                            </p>
                                                            <p className="truncate font-mono text-muted-foreground text-xs sm:hidden">
                                                                /{tenant.slug}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="hidden px-4 py-3 sm:table-cell">
                                                    <Badge
                                                        variant="outline"
                                                        className="font-mono font-normal"
                                                    >
                                                        /{tenant.slug}
                                                    </Badge>
                                                </td>
                                                <td className="hidden px-4 py-3 md:table-cell">
                                                    <span
                                                        className="whitespace-nowrap text-muted-foreground tabular-nums"
                                                        suppressHydrationWarning
                                                    >
                                                        {timeAgo(
                                                            tenant.deleted_at,
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="hidden sm:inline-flex"
                                                            onClick={() =>
                                                                setRestoring(
                                                                    tenant,
                                                                )
                                                            }
                                                        >
                                                            <RotateCcw className="size-3.5" />
                                                            Restore
                                                        </Button>
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-8"
                                                                    aria-label={`Actions for ${tenant.name}`}
                                                                >
                                                                    <MoreHorizontal className="size-4" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent
                                                                align="end"
                                                                className="w-52"
                                                            >
                                                                <DropdownMenuItem
                                                                    className="sm:hidden"
                                                                    onSelect={() =>
                                                                        setRestoring(
                                                                            tenant,
                                                                        )
                                                                    }
                                                                >
                                                                    <RotateCcw className="size-4" />
                                                                    Restore
                                                                </DropdownMenuItem>
                                                                <DropdownMenuSeparator className="sm:hidden" />
                                                                <DropdownMenuItem
                                                                    variant="destructive"
                                                                    onSelect={() =>
                                                                        setPurging(
                                                                            tenant,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="size-4" />
                                                                    Delete
                                                                    permanently
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {tenants.data.length > 0 && (
                            <div className="flex flex-col items-center justify-between gap-4 border-border border-t px-4 py-3 sm:flex-row">
                                <p className="text-muted-foreground text-sm">
                                    Showing{' '}
                                    <span className="font-medium text-foreground tabular-nums">
                                        {tenants.from}
                                    </span>
                                    –
                                    <span className="font-medium text-foreground tabular-nums">
                                        {tenants.to}
                                    </span>{' '}
                                    of{' '}
                                    <span className="font-medium text-foreground tabular-nums">
                                        {tenants.total}
                                    </span>{' '}
                                    archived
                                </p>
                                <div className="flex items-center gap-3">
                                    <div className="flex items-center gap-2">
                                        <span className="hidden text-muted-foreground text-sm sm:inline">
                                            Per page
                                        </span>
                                        <Select
                                            value={String(tenants.per_page)}
                                            disabled={loading}
                                            onValueChange={(value) =>
                                                visit('/admin/tenants/trashed', {
                                                    search:
                                                        filters.search ||
                                                        undefined,
                                                    per_page: Number(value),
                                                })
                                            }
                                        >
                                            <SelectTrigger
                                                className="h-8 w-[4.25rem]"
                                                aria-label="Rows per page"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {PER_PAGE_OPTIONS.map((n) => (
                                                    <SelectItem
                                                        key={n}
                                                        value={String(n)}
                                                    >
                                                        {n}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-8"
                                            disabled={
                                                !tenants.prev_page_url || loading
                                            }
                                            onClick={() =>
                                                visit(tenants.prev_page_url)
                                            }
                                            aria-label="Previous page"
                                        >
                                            <ChevronLeft className="size-4" />
                                        </Button>
                                        <span className="px-1 text-muted-foreground text-sm tabular-nums">
                                            Page {tenants.current_page} of{' '}
                                            {tenants.last_page}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-8"
                                            disabled={
                                                !tenants.next_page_url || loading
                                            }
                                            onClick={() =>
                                                visit(tenants.next_page_url)
                                            }
                                            aria-label="Next page"
                                        >
                                            <ChevronRight className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Restore confirmation */}
            <Dialog
                open={restoring !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        setRestoring(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Restore tenant</DialogTitle>
                        <DialogDescription>
                            Bring “{restoring?.name}” back online? Its workspace
                            (/{restoring?.slug}) becomes accessible again.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setRestoring(null)}
                        >
                            Cancel
                        </Button>
                        <Button type="button" onClick={confirmRestore}>
                            <RotateCcw className="size-4" />
                            Restore
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Permanent delete — type-to-confirm */}
            <Dialog
                open={purging !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        closePurge();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete tenant permanently</DialogTitle>
                        <DialogDescription>
                            This permanently deletes “{purging?.name}” and drops
                            its database. This cannot be undone. Type{' '}
                            <span className="font-mono font-medium text-foreground">
                                {purging?.slug}
                            </span>{' '}
                            to confirm.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="confirm-slug" className="sr-only">
                            Type the tenant slug to confirm
                        </Label>
                        <Input
                            id="confirm-slug"
                            value={confirmText}
                            onChange={(event) =>
                                setConfirmText(event.target.value)
                            }
                            autoComplete="off"
                            placeholder={purging?.slug}
                            className="font-mono"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={closePurge}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={confirmText !== purging?.slug}
                            onClick={confirmPurge}
                        >
                            <Trash2 className="size-4" />
                            Delete permanently
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </CentralAdminLayout>
    );
}
```

- [ ] **Step 2: Verify the frontend compiles**

Run: `bun run check && bun run types:check`
Expected: 0 errors.

- [ ] **Step 3: Build**

Run: `bun run build`
Expected: build succeeds.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/admin/tenants/trashed.tsx
git commit -m "feat(admin): archived tenants page with restore + force delete"
```

---

## Task 8: Full verification & wrap-up

**Files:** none (verification only)

- [ ] **Step 1: Run the full backend suite**

Run: `php artisan test --compact`
Expected: all tests PASS (existing + new Task 1–4 tests).

- [ ] **Step 2: Frontend gates**

Run: `bun run check:ci && bun run types:check && bun run build`
Expected: 0 Biome errors, 0 type errors, successful build.

- [ ] **Step 3: PHP formatting**

Run: `vendor/bin/pint --dirty`
Expected: clean (no changes) or auto-fixes committed.

- [ ] **Step 4: Manual smoke (optional, if a dev DB is available)**

Provision a throwaway tenant, soft-delete it from `/admin/tenants` (confirm it disappears from the list and its `/{slug}/login` 404s), open `/admin/tenants/trashed`, restore it (confirm `/{slug}/login` works again), soft-delete again, then permanently delete (type the slug) and confirm the row is gone and the database dropped.

- [ ] **Step 5: Final commit if anything remains**

```bash
git add -A
git commit -m "chore(admin): tenant soft/force delete verification pass"
```

---

## Self-review notes (coverage map)

- Spec §"Critical constraint" (DB kept on soft delete) → **Task 1**.
- Spec §"Page structure" (dashboard stats-only; list at `/admin/tenants`) → **Tasks 2, 3, 5, 6**.
- Spec §Backend routes/controllers → **Tasks 3, 4**.
- Spec §"Access control" (trashed → 404) → **Task 4** (`makes a soft-deleted tenant inaccessible`).
- Spec §"Edge case: reusing a trashed slug" (message) → **Task 4 Step 5**.
- Spec §Frontend (dashboard, index, trashed, sidebar) → **Tasks 5, 6, 7**.
- Spec §Testing list items 1–9 → covered across **Tasks 1–4**; frontend gates → **Task 8**.
- Restore lifecycle (chosen "Restore + Force delete") → **Task 4** (restore route/method/test) + **Task 7** (restore dialog).
- Force-delete type-to-confirm → **Task 7** (`purging`/`confirmText` guard).
