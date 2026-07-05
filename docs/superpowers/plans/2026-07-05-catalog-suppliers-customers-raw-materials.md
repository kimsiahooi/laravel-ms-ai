# Catalog Phase 2 — Suppliers, Customers, Raw materials — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add three per-tenant catalog CRUD modules — Suppliers, Customers, Raw materials — each cloning the existing Categories module (model + FormRequest + resource controller + Inertia page + Pest tests), reachable under `/{slug}/…`.

**Architecture:** Each entity is an independent Eloquent model in the tenant DB with `SoftDeletes`, a resource controller (index paginate+search / store / update / destroy → `back()`+flash), a FormRequest, one Inertia page cloned from `categories/index.tsx`, a tenant-sidebar nav item, and a Pest feature test. Suppliers and Customers share an identical shape (contact fields; email unique+nullable); Raw materials differ (name, sku unique, unit, min_stock).

**Tech Stack:** Laravel 13, stancl/tenancy v3 (multi-DB, path/slug), Inertia v3 + React 19 + TS, Tailwind v4, shadcn/ui (`Dialog`), Pest. Package manager: Bun. String route paths (no Ziggy).

**Spec:** `docs/superpowers/specs/2026-07-05-catalog-suppliers-customers-raw-materials-design.md`

**Reference (clone these):**
- `app/Models/Category.php`, `app/Http/Requests/Tenant/CategoryRequest.php`,
  `app/Http/Controllers/Tenant/CategoryController.php`,
  `database/migrations/tenant/2026_07_05_000001_create_categories_table.php`,
  `resources/js/pages/tenant/categories/index.tsx`,
  `tests/Feature/Tenant/CategoryTest.php`,
  `resources/js/components/tenant/tenant-sidebar.tsx`, `routes/tenant.php`.

**Conventions:**
- PHP: `vendor/bin/pint --dirty` before each commit. Frontend: `bun run check` then `bun run types:check`; `bun run build` must succeed.
- Tests: `php artisan test --filter=Name`. Central DB uses `RefreshDatabase`; tenant tests provision a real tenant via `ProvisionTenant` and `Tests\TestCase` drops `msai_test_tenant_*` DBs in teardown.
- **`config/inertia.php` has `testing.ensure_pages_exist = true`** — a controller's `->component('tenant/x/index')` assertion FAILS until that `.tsx` page file exists. So within each module task, create the page (and nav) in the same task as the backend; the module's tests only go fully green once the page exists.
- Tenant auth in tests: provision `acme`, then `loginAsAcmeUser()` (moved to `Pest.php` in Task 0); guests are redirected to `route('tenant.login', ['tenant' => 'acme'])`.

---

## Task 0: Move `loginAsAcmeUser` into Pest.php (shared helper)

`loginAsAcmeUser()` is a global function in `CategoryTest.php`; the new test files
can't redeclare it. Move it to `tests/Pest.php` so all catalog tests share one.

**Files:**
- Modify: `tests/Pest.php`
- Modify: `tests/Feature/Tenant/CategoryTest.php`

- [ ] **Step 1: Add the helper to `tests/Pest.php`**

Append to the "Functions" area of `tests/Pest.php` (next to `makeTenants`):

```php
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
```

- [ ] **Step 2: Remove the local copy from `CategoryTest.php`**

Delete this block from `tests/Feature/Tenant/CategoryTest.php` (lines ~17-23):

```php
function loginAsAcmeUser(): void
{
    test()->post('/acme/login', [
        'email' => 'ada@acme.test',
        'password' => 'password123',
    ]);
}
```

- [ ] **Step 3: Confirm nothing broke**

Run: `php artisan test --filter=CategoryTest`
Expected: PASS (6 tests) — CategoryTest now uses the Pest.php helper.

- [ ] **Step 4: Commit**

```bash
vendor/bin/pint --dirty
git add tests/Pest.php tests/Feature/Tenant/CategoryTest.php
git commit -m "test(tenant): share loginAsAcmeUser helper via Pest.php"
```

---

## Task 1: Suppliers module

**Files:**
- Create: `app/Models/Supplier.php`
- Create: `database/migrations/tenant/2026_07_05_000003_create_suppliers_table.php`
- Create: `app/Http/Requests/Tenant/SupplierRequest.php`
- Create: `app/Http/Controllers/Tenant/SupplierController.php`
- Modify: `routes/tenant.php`
- Create: `resources/js/components/ui/textarea.tsx` (shadcn primitive — doesn't exist yet)
- Create: `resources/js/pages/tenant/suppliers/index.tsx`
- Modify: `resources/js/components/tenant/tenant-sidebar.tsx`
- Test: `tests/Feature/Tenant/SupplierTest.php`

- [ ] **Step 1: Write the failing test** — Create `tests/Feature/Tenant/SupplierTest.php`:

```php
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
            'email' => 'metals@acme.test',
            'phone' => '+60 12-345 6789',
            'address' => '1 Foundry Rd',
            'notes' => 'Primary steel supplier',
        ])
        ->assertRedirect('/acme/suppliers')
        ->assertSessionHas('success');

    $this->tenant->run(function () {
        expect(Supplier::where('name', 'Acme Metals')->exists())->toBeTrue();
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
        ->assertSessionHas('success');

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
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(Supplier::find($id))->toBeNull()
            ->and(Supplier::withTrashed()->find($id))->not->toBeNull();
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=SupplierTest`
Expected: FAIL — no `Supplier` model / `/acme/suppliers` route yet.

- [ ] **Step 3: Create the model** — `app/Models/Supplier.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'email', 'phone', 'address', 'notes'])]
class Supplier extends Model
{
    use SoftDeletes;
}
```

- [ ] **Step 4: Create the migration** — `database/migrations/tenant/2026_07_05_000003_create_suppliers_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-tenant catalog: suppliers. Email is unique within the tenant (nullable —
// MySQL permits multiple NULLs), name is not unique.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
```

- [ ] **Step 5: Create the FormRequest** — `app/Http/Requests/Tenant/SupplierRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $ignoreId = $supplier instanceof Supplier ? $supplier->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'string', 'email', 'max:255',
                Rule::unique('suppliers', 'email')->ignore($ignoreId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 6: Create the controller** — `app/Http/Controllers/Tenant/SupplierController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Requests\Tenant\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController
{
    /** @var array<int, int> */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = (int) $request->integer('per_page', 10);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 10;
        }

        $suppliers = Supplier::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $group) use ($search): void {
                    $group->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Supplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'notes' => $supplier->notes,
                'created_at' => $supplier->created_at,
            ]);

        return Inertia::render('tenant/suppliers/index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return back()->with('success', 'Supplier created.');
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return back()->with('success', 'Supplier deleted.');
    }
}
```

- [ ] **Step 7: Register the route** — in `routes/tenant.php`, inside the `auth:web` group, in the `// Catalog` section (right after the `categories` resource line), add:

```php
Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
```

Add the import at the top with the other tenant controller `use` statements:

```php
use App\Http\Controllers\Tenant\SupplierController;
```

- [ ] **Step 8: Create the `Textarea` component (needed for address/notes)** — this shadcn primitive doesn't exist yet. Create `resources/js/components/ui/textarea.tsx`:

```tsx
import type * as React from 'react';

import { cn } from '@/lib/utils';

function Textarea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn(
                'flex field-sizing-content min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40',
                className,
            )}
            {...props}
        />
    );
}

export { Textarea };
```

- [ ] **Step 9: Create the Inertia page** — Create `resources/js/pages/tenant/suppliers/index.tsx` by copying `resources/js/pages/tenant/categories/index.tsx` and adapting:

```bash
mkdir -p resources/js/pages/tenant/suppliers
cp resources/js/pages/tenant/categories/index.tsx resources/js/pages/tenant/suppliers/index.tsx
```

Then edit `resources/js/pages/tenant/suppliers/index.tsx`:
1. Rename the default export `CategoriesIndex` → `SuppliersIndex` (match the actual name in the file).
2. Replace the `Category` type with:
   ```tsx
   type Supplier = {
       id: number;
       name: string;
       email: string | null;
       phone: string | null;
       address: string | null;
       notes: string | null;
       created_at: string;
   };
   ```
   and update `PageProps` so the paginator/filters key is `suppliers` (was `categories`); update the `usePage().props` destructure accordingly.
3. Change the base path: `const base = \`/${tenant.slug}/suppliers\`;` and the breadcrumbs `{ title: 'Suppliers', href: base }`, `<Head title="Suppliers" />`, page heading "Suppliers".
4. Replace every `categories`/`category` identifier (state, props, mapping, toasts) with `suppliers`/`supplier`.
5. Table columns → **Name · Email · Phone** (+ the actions dropdown). Show `—` for null email/phone.
6. Create/Edit form fields → replace the single name/description pair with: **name** (required text), **email** (type=email), **phone** (text), **address** (`Textarea`), **notes** (`Textarea`), each with its own controlled state and an `InputError` bound to `errors.<field>`. Import `Textarea` from `@/components/ui/textarea`.
7. Controlled edit state: when opening the edit dialog, populate all five fields from the row (use empty string for nulls); reset on close/create.
8. Search placeholder → "Search name or email…"; the debounced search still posts `search`/`per_page` to `base`.
9. Empty state copy → "No suppliers yet"; no-results copy → "No suppliers match …".

Keep everything else (pagination, per-page select, delete confirmation Dialog, sr-only status region, `router.delete` with preserveScroll + flash toast) identical to the categories page.

- [ ] **Step 10: Add the sidebar nav item** — in `resources/js/components/tenant/tenant-sidebar.tsx`, add `Truck` to the lucide import and add this nav item to `mainNavItems` after the Categories entry:

```tsx
{
    title: 'Suppliers',
    href: `/${slug}/suppliers`,
    icon: Truck,
},
```

- [ ] **Step 11: Run tests + gates**

Run: `php artisan test --filter=SupplierTest`
Expected: PASS (7 tests) — the `->component('tenant/suppliers/index')` assertion now resolves because the page exists.

Run: `bun run check && bun run types:check && bun run build`
Expected: 0 Biome errors, 0 type errors, build succeeds.

- [ ] **Step 12: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/Supplier.php database/migrations/tenant/2026_07_05_000003_create_suppliers_table.php app/Http/Requests/Tenant/SupplierRequest.php app/Http/Controllers/Tenant/SupplierController.php routes/tenant.php resources/js/components/ui/textarea.tsx resources/js/pages/tenant/suppliers/index.tsx resources/js/components/tenant/tenant-sidebar.tsx tests/Feature/Tenant/SupplierTest.php
git commit -m "feat(catalog): suppliers CRUD module"
```

---

## Task 2: Customers module

Identical shape to Suppliers — clone the Supplier files, substituting Customer / customer / customers, and the `Contact` sidebar icon.

**Files:**
- Create: `app/Models/Customer.php`
- Create: `database/migrations/tenant/2026_07_05_000004_create_customers_table.php`
- Create: `app/Http/Requests/Tenant/CustomerRequest.php`
- Create: `app/Http/Controllers/Tenant/CustomerController.php`
- Modify: `routes/tenant.php`
- Create: `resources/js/pages/tenant/customers/index.tsx`
- Modify: `resources/js/components/tenant/tenant-sidebar.tsx`
- Test: `tests/Feature/Tenant/CustomerTest.php`

- [ ] **Step 1: Write the failing test** — Create `tests/Feature/Tenant/CustomerTest.php` — same as `SupplierTest.php` from Task 1 with every `Supplier`→`Customer`, `supplier`→`customer`, `suppliers`→`customers`, and the component `tenant/customers/index`. Full text:

```php
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
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=CustomerTest` — Expected: FAIL (no Customer model/route).

- [ ] **Step 3: Model** — `app/Models/Customer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'email', 'phone', 'address', 'notes'])]
class Customer extends Model
{
    use SoftDeletes;
}
```

- [ ] **Step 4: Migration** — `database/migrations/tenant/2026_07_05_000004_create_customers_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-tenant catalog: customers. Email unique within the tenant (nullable).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
```

- [ ] **Step 5: FormRequest** — `app/Http/Requests/Tenant/CustomerRequest.php` — the `SupplierRequest` with Supplier→Customer, `suppliers`→`customers`, `$this->route('customer')`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');
        $ignoreId = $customer instanceof Customer ? $customer->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'string', 'email', 'max:255',
                Rule::unique('customers', 'email')->ignore($ignoreId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 6: Controller** — `app/Http/Controllers/Tenant/CustomerController.php` — the `SupplierController` with Supplier→Customer, `suppliers`→`customers`, renders `tenant/customers/index` with a `customers` prop:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Requests\Tenant\CustomerRequest;
use App\Models\Customer;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController
{
    /** @var array<int, int> */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = (int) $request->integer('per_page', 10);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 10;
        }

        $customers = Customer::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $group) use ($search): void {
                    $group->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'notes' => $customer->notes,
                'created_at' => $customer->created_at,
            ]);

        return Inertia::render('tenant/customers/index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        Customer::create($request->validated());

        return back()->with('success', 'Customer created.');
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return back()->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return back()->with('success', 'Customer deleted.');
    }
}
```

- [ ] **Step 7: Route** — in `routes/tenant.php`, add the import `use App\Http\Controllers\Tenant\CustomerController;` and, in the `// Catalog` block after the `suppliers` line:

```php
Route::resource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);
```

- [ ] **Step 8: Page** — copy the Suppliers page and rename:

```bash
mkdir -p resources/js/pages/tenant/customers
cp resources/js/pages/tenant/suppliers/index.tsx resources/js/pages/tenant/customers/index.tsx
```

Edit `resources/js/pages/tenant/customers/index.tsx`: rename `Supplier`→`Customer`, `suppliers`→`customers`, `supplier`→`customer`, the export `SuppliersIndex`→`CustomersIndex`, `base = \`/${tenant.slug}/customers\``, breadcrumb/heading/`<Head>` "Customers", search placeholder "Search name or email…", empty/no-results copy → customers. Field set (name/email/phone/address/notes) and columns (Name · Email · Phone) are unchanged from Suppliers.

- [ ] **Step 9: Sidebar** — in `resources/js/components/tenant/tenant-sidebar.tsx`, add `Contact` to the lucide import and a nav item after Suppliers:

```tsx
{
    title: 'Customers',
    href: `/${slug}/customers`,
    icon: Contact,
},
```

- [ ] **Step 10: Run tests + gates**

Run: `php artisan test --filter=CustomerTest` — Expected: PASS (7).
Run: `bun run check && bun run types:check && bun run build` — Expected: clean.

- [ ] **Step 11: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/Customer.php database/migrations/tenant/2026_07_05_000004_create_customers_table.php app/Http/Requests/Tenant/CustomerRequest.php app/Http/Controllers/Tenant/CustomerController.php routes/tenant.php resources/js/pages/tenant/customers/index.tsx resources/js/components/tenant/tenant-sidebar.tsx tests/Feature/Tenant/CustomerTest.php
git commit -m "feat(catalog): customers CRUD module"
```

---

## Task 3: Raw materials module

Different field set: `name`, `sku` (unique), `unit`, `min_stock` (decimal, default 0).
Kebab URI `raw-materials` with an explicit `rawMaterial` route parameter.

**Files:**
- Create: `app/Models/RawMaterial.php`
- Create: `database/migrations/tenant/2026_07_05_000005_create_raw_materials_table.php`
- Create: `app/Http/Requests/Tenant/RawMaterialRequest.php`
- Create: `app/Http/Controllers/Tenant/RawMaterialController.php`
- Modify: `routes/tenant.php`
- Create: `resources/js/pages/tenant/raw-materials/index.tsx`
- Modify: `resources/js/components/tenant/tenant-sidebar.tsx`
- Test: `tests/Feature/Tenant/RawMaterialTest.php`

- [ ] **Step 1: Write the failing test** — Create `tests/Feature/Tenant/RawMaterialTest.php`:

```php
<?php

use App\Actions\ProvisionTenant;
use App\Models\RawMaterial;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('redirects a guest from the raw materials page to the tenant login', function () {
    $this->get('/acme/raw-materials')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('lists a tenant’s raw materials, paginated', function () {
    $this->tenant->run(function () {
        RawMaterial::create(['name' => 'Steel Rod', 'sku' => 'RM-001', 'unit' => 'kg']);
        RawMaterial::create(['name' => 'Copper Wire', 'sku' => 'RM-002', 'unit' => 'm']);
    });

    loginAsAcmeUser();

    $this->get('/acme/raw-materials?per_page=10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/raw-materials/index')
            ->has('rawMaterials.data', 2)
            ->where('rawMaterials.total', 2)
            ->where('filters.per_page', 10)
        );
});

it('creates a raw material and defaults min_stock to 0', function () {
    loginAsAcmeUser();

    $this->from('/acme/raw-materials')
        ->post('/acme/raw-materials', [
            'name' => 'Steel Rod',
            'sku' => 'RM-001',
            'unit' => 'kg',
        ])
        ->assertRedirect('/acme/raw-materials')
        ->assertSessionHas('success');

    $this->tenant->run(function () {
        $rm = RawMaterial::firstWhere('sku', 'RM-001');
        expect($rm)->not->toBeNull()
            ->and((float) $rm->min_stock)->toBe(0.0);
    });
});

it('rejects a duplicate sku', function () {
    $this->tenant->run(fn () => RawMaterial::create(['name' => 'Steel', 'sku' => 'RM-001', 'unit' => 'kg']));

    loginAsAcmeUser();

    $this->from('/acme/raw-materials')
        ->post('/acme/raw-materials', ['name' => 'Other', 'sku' => 'RM-001', 'unit' => 'kg'])
        ->assertRedirect('/acme/raw-materials')
        ->assertSessionHasErrors('sku');
});

it('updates a raw material', function () {
    $id = $this->tenant->run(
        fn () => RawMaterial::create(['name' => 'Steel', 'sku' => 'RM-001', 'unit' => 'kg'])->id,
    );

    loginAsAcmeUser();

    $this->from('/acme/raw-materials')
        ->put("/acme/raw-materials/{$id}", [
            'name' => 'Steel Rod', 'sku' => 'RM-001', 'unit' => 'kg', 'min_stock' => 25.5,
        ])
        ->assertRedirect('/acme/raw-materials')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect((float) RawMaterial::find($id)->min_stock)->toBe(25.5);
    });
});

it('soft-deletes a raw material', function () {
    $id = $this->tenant->run(
        fn () => RawMaterial::create(['name' => 'Steel', 'sku' => 'RM-001', 'unit' => 'kg'])->id,
    );

    loginAsAcmeUser();

    $this->from('/acme/raw-materials')
        ->delete("/acme/raw-materials/{$id}")
        ->assertRedirect('/acme/raw-materials')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(RawMaterial::find($id))->toBeNull()
            ->and(RawMaterial::withTrashed()->find($id))->not->toBeNull();
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=RawMaterialTest` — Expected: FAIL.

- [ ] **Step 3: Model** — `app/Models/RawMaterial.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'sku', 'unit', 'min_stock'])]
class RawMaterial extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_stock' => 'decimal:4'];
    }
}
```

- [ ] **Step 4: Migration** — `database/migrations/tenant/2026_07_05_000005_create_raw_materials_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-tenant catalog: raw materials. SKU is unique within the tenant.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku', 100)->unique();
            $table->string('unit', 20);
            $table->decimal('min_stock', 12, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_materials');
    }
};
```

- [ ] **Step 5: FormRequest** — `app/Http/Requests/Tenant/RawMaterialRequest.php` (coerces a blank `min_stock` to 0):

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\RawMaterial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RawMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (in_array($this->input('min_stock'), [null, ''], true)) {
            $this->merge(['min_stock' => 0]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rawMaterial = $this->route('rawMaterial');
        $ignoreId = $rawMaterial instanceof RawMaterial ? $rawMaterial->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required', 'string', 'max:100',
                Rule::unique('raw_materials', 'sku')->ignore($ignoreId),
            ],
            'unit' => ['required', 'string', 'max:20'],
            'min_stock' => ['required', 'numeric', 'min:0'],
        ];
    }
}
```

- [ ] **Step 6: Controller** — `app/Http/Controllers/Tenant/RawMaterialController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Requests\Tenant\RawMaterialRequest;
use App\Models\RawMaterial;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RawMaterialController
{
    /** @var array<int, int> */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = (int) $request->integer('per_page', 10);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 10;
        }

        $rawMaterials = RawMaterial::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $group) use ($search): void {
                    $group->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (RawMaterial $rawMaterial): array => [
                'id' => $rawMaterial->id,
                'name' => $rawMaterial->name,
                'sku' => $rawMaterial->sku,
                'unit' => $rawMaterial->unit,
                'min_stock' => $rawMaterial->min_stock,
                'created_at' => $rawMaterial->created_at,
            ]);

        return Inertia::render('tenant/raw-materials/index', [
            'rawMaterials' => $rawMaterials,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(RawMaterialRequest $request): RedirectResponse
    {
        RawMaterial::create($request->validated());

        return back()->with('success', 'Raw material created.');
    }

    public function update(RawMaterialRequest $request, RawMaterial $rawMaterial): RedirectResponse
    {
        $rawMaterial->update($request->validated());

        return back()->with('success', 'Raw material updated.');
    }

    public function destroy(RawMaterial $rawMaterial): RedirectResponse
    {
        $rawMaterial->delete();

        return back()->with('success', 'Raw material deleted.');
    }
}
```

- [ ] **Step 7: Route** — in `routes/tenant.php`, add `use App\Http\Controllers\Tenant\RawMaterialController;` and, in the `// Catalog` block after `customers`:

```php
Route::resource('raw-materials', RawMaterialController::class)
    ->parameters(['raw-materials' => 'rawMaterial'])
    ->only(['index', 'store', 'update', 'destroy']);
```

- [ ] **Step 8: Page** — copy the Suppliers page and adapt to the raw-material fields:

```bash
mkdir -p resources/js/pages/tenant/raw-materials
cp resources/js/pages/tenant/suppliers/index.tsx resources/js/pages/tenant/raw-materials/index.tsx
```

Edit `resources/js/pages/tenant/raw-materials/index.tsx`:
1. Type:
   ```tsx
   type RawMaterial = {
       id: number;
       name: string;
       sku: string;
       unit: string;
       min_stock: string;
       created_at: string;
   };
   ```
   (`min_stock` is a `string` because Laravel's `decimal:4` cast serializes to a string.) Prop/paginator key `rawMaterials`; export `RawMaterialsIndex`.
2. `const base = \`/${tenant.slug}/raw-materials\`;`; breadcrumb/heading/`<Head>` "Raw materials".
3. Table columns → **Name · SKU · Unit · Min stock** (+ actions). Render `min_stock` right-aligned/tabular.
4. Create/Edit form fields → **name** (required), **sku** (required), **unit** (required), **min_stock** (`<Input type="number" min={0} step="any">`, default `'0'`). Each with controlled state + `InputError`. Remove the email/phone/address/notes fields **and the now-unused `Textarea` import** (raw materials has no multi-line fields).
5. Edit-populate: name/sku/unit from the row; `min_stock` from `String(row.min_stock ?? '0')`.
6. Search placeholder → "Search name or SKU…".
7. Empty/no-results copy → "raw materials".

- [ ] **Step 9: Sidebar** — in `resources/js/components/tenant/tenant-sidebar.tsx`, add `Boxes` to the lucide import and a nav item after Customers:

```tsx
{
    title: 'Raw materials',
    href: `/${slug}/raw-materials`,
    icon: Boxes,
},
```

- [ ] **Step 10: Run tests + gates**

Run: `php artisan test --filter=RawMaterialTest` — Expected: PASS (6).
Run: `bun run check && bun run types:check && bun run build` — Expected: clean.

- [ ] **Step 11: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/RawMaterial.php database/migrations/tenant/2026_07_05_000005_create_raw_materials_table.php app/Http/Requests/Tenant/RawMaterialRequest.php app/Http/Controllers/Tenant/RawMaterialController.php routes/tenant.php resources/js/pages/tenant/raw-materials/index.tsx resources/js/components/tenant/tenant-sidebar.tsx tests/Feature/Tenant/RawMaterialTest.php
git commit -m "feat(catalog): raw materials CRUD module"
```

---

## Task 4: Full verification

**Files:** none (verification only)

- [ ] **Step 1: Backend suite**

Run: `php artisan test --compact`
Expected: all pass (existing + the 3 new modules' tests + Task 0 CategoryTest still green).

- [ ] **Step 2: Frontend gates**

Run: `bun run check:ci && bun run types:check && bun run build`
Expected: 0 Biome errors, 0 type errors, build succeeds.

- [ ] **Step 3: PHP formatting**

Run: `vendor/bin/pint --test`
Expected: clean.

- [ ] **Step 4: (Optional) manual smoke** — if a dev tenant exists, run `php artisan tenants:migrate`, log into `/{slug}/…`, and confirm the three new sidebar items reach working list/create/edit/delete pages.

---

## Self-review coverage map

- Spec "Suppliers/Customers" table + validation (name required, email unique+nullable, phone/address/notes) → **Tasks 1, 2** (model/migration/request + tests incl. duplicate-email + multiple-null-email).
- Spec "Raw materials" (name, sku unique, unit, min_stock decimal default 0) → **Task 3** (incl. min_stock-defaults-to-0 + duplicate-sku tests; `decimal:4` cast).
- Spec "Controllers" (paginate+search over name+email / name+sku, `back()`+flash) → controllers in Tasks 1–3.
- Spec "Routes" (`Route::resource(...)->only([...])`; raw-materials `->parameters([...])`) → route steps in Tasks 1–3.
- Spec "Frontend" (clone categories page; fields/columns/search per entity; sidebar nav) → page + sidebar steps in Tasks 1–3.
- Spec "Testing" (guest redirect, list, create, duplicate, update, soft-delete) → the test files in Tasks 1–3.
- Shared `loginAsAcmeUser` (avoids redeclare across test files) → **Task 0**.
- `ensure_pages_exist` coupling handled by building page within each module task → Tasks 1–3 Step 10 runs green.
