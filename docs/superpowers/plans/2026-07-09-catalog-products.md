# Products (Catalog) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the Products module — per-tenant CRUD with image upload and category/supplier pickers — as the final Phase 2 catalog feature.

**Architecture:** Follows the existing catalog pattern exactly (server-side `<DataTable>` list + create/edit dialog, `back()->with('success')` redirects, soft deletes). New ground: a single per-tenant image (native Laravel Storage on the tenant-suffixed `public` disk, served via `tenant_asset()`), two nullable foreign keys (category, supplier) chosen through a searchable shadcn combobox.

**Tech Stack:** Laravel 13, Inertia v3, React 19 + TypeScript, Tailwind v4, `@tanstack/react-table` (manual mode), shadcn/ui, `cmdk` + Radix Popover (new), Pest, `stancl/tenancy` (multi-DB).

**Spec:** `docs/superpowers/specs/2026-07-09-catalog-products-design.md`

---

## File Structure

**Create:**
- `database/migrations/tenant/<ts>_create_products_table.php` — products schema
- `app/Models/Product.php` — model (SoftDeletes, relationships, `image_url` accessor)
- `app/Http/Requests/Tenant/ProductRequest.php` — validation
- `app/Http/Controllers/Tenant/ProductController.php` — index/store/update/destroy
- `resources/js/components/ui/command.tsx` — shadcn primitive (via CLI)
- `resources/js/components/ui/popover.tsx` — shadcn primitive (via CLI)
- `resources/js/components/combobox.tsx` — reusable searchable picker
- `resources/js/pages/tenant/products/index.tsx` — list + dialogs
- `tests/Feature/Tenant/ProductTest.php` — feature tests

**Modify:**
- `routes/tenant.php` — register the products resource route
- `resources/js/components/tenant/tenant-sidebar.tsx` — add "Products" nav item
- `tests/TestCase.php` — delete test tenants' storage dirs in `purgeTenants()`

---

## Task 1: Combobox UI infrastructure

**Files:**
- Create: `resources/js/components/ui/command.tsx`, `resources/js/components/ui/popover.tsx` (shadcn CLI)
- Create: `resources/js/components/combobox.tsx`
- Modify: `package.json` / `bun.lock` (new deps)

- [ ] **Step 1: Install deps + shadcn primitives**

The project already has `components.json` (new-york style, `@/components/ui`). Add the two missing primitives and their libs:

```bash
bun add cmdk @radix-ui/react-popover
bunx --bun shadcn@latest add command popover --yes --overwrite
```

Expected: `resources/js/components/ui/command.tsx` and `resources/js/components/ui/popover.tsx` created. (`ui/*` is excluded from Biome — leave these verbatim.)

If the CLI cannot reach the network, add the two files manually from https://ui.shadcn.com/docs/components/combobox (command + popover source) — same result.

- [ ] **Step 2: Create the reusable combobox**

Create `resources/js/components/combobox.tsx`:

```tsx
import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type ComboboxOption = { value: string; label: string };

type ComboboxProps = {
    options: ComboboxOption[];
    value: string;
    onChange: (value: string) => void;
    id?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    noneLabel?: string;
    invalid?: boolean;
    describedBy?: string;
};

export function Combobox({
    options,
    value,
    onChange,
    id,
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    emptyText = 'No results.',
    noneLabel = 'None',
    invalid,
    describedBy,
}: ComboboxProps) {
    const [open, setOpen] = useState(false);
    const selected = options.find((option) => option.value === value);

    const select = (next: string) => {
        onChange(next);
        setOpen(false);
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    aria-haspopup="listbox"
                    aria-expanded={open}
                    aria-invalid={invalid}
                    aria-describedby={describedBy}
                    className="w-full justify-between font-normal"
                >
                    <span className={cn(!selected && 'text-muted-foreground')}>
                        {selected ? selected.label : placeholder}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-[var(--radix-popover-trigger-width)] p-0"
            >
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        <CommandGroup>
                            <CommandItem
                                value="__none__"
                                onSelect={() => select('')}
                            >
                                <Check
                                    className={cn(
                                        'size-4',
                                        value === '' ? 'opacity-100' : 'opacity-0',
                                    )}
                                />
                                <span className="text-muted-foreground">
                                    {noneLabel}
                                </span>
                            </CommandItem>
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={option.label}
                                    onSelect={() => select(option.value)}
                                >
                                    <Check
                                        className={cn(
                                            'size-4',
                                            value === option.value
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    {option.label}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
```

- [ ] **Step 3: Verify frontend**

Run: `bun run check && bun run types:check`
Expected: 0 errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components package.json bun.lock components.json
git commit -m "feat(ui): add searchable combobox (command + popover)"
```

---

## Task 2: Products migration + model

**Files:**
- Create: `database/migrations/tenant/<ts>_create_products_table.php`
- Create: `app/Models/Product.php`

- [ ] **Step 1: Generate the migration file**

Run: `php artisan make:migration create_products_table --path=database/migrations/tenant`
Expected: creates `database/migrations/tenant/2026_07_09_XXXXXX_create_products_table.php` (its timestamp sorts after `categories`/`suppliers`, so the FKs resolve).

- [ ] **Step 2: Replace the migration body**

Overwrite the generated file with:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-tenant catalog: products. The anchor entity later phases reference
// (sales order items, BOM, production). Only `sku` is unique; `barcode` is
// optional and not unique. category_id/supplier_id are nullOnDelete (fires on
// a real force-delete of the parent, not a soft delete).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()
                ->constrained('categories')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()
                ->constrained('suppliers')->nullOnDelete();
            $table->unsignedInteger('min_stock')->default(0);
            $table->string('unit');
            $table->string('image')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

- [ ] **Step 3: Create the model**

Create `app/Models/Product.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Product in the per-tenant catalog. Lives on the default connection, which
 * InitializeTenancyByPath has switched to the tenant database.
 *
 * @property int $id
 * @property string $name
 * @property string $sku
 * @property string|null $barcode
 * @property string|null $description
 * @property int|null $category_id
 * @property int|null $supplier_id
 * @property int $min_stock
 * @property string $unit
 * @property string|null $image
 * @property-read string|null $image_url
 */
#[Fillable([
    'name', 'sku', 'barcode', 'description',
    'category_id', 'supplier_id', 'min_stock', 'unit', 'image',
])]
class Product extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_stock' => 'integer'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Public URL for the stored image, resolved through the tenant asset route.
     * Null when no image is set. Only valid inside a tenant context (all product
     * routes are).
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->image === null
                ? null
                : tenant_asset($this->image),
        );
    }
}
```

- [ ] **Step 4: Lint PHP**

Run: `vendor/bin/pint --dirty`
Expected: files formatted, no errors.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/tenant app/Models/Product.php
git commit -m "feat(products): add products table migration and model"
```

---

## Task 3: List — controller index, route, page, sidebar, test

**Files:**
- Modify: `routes/tenant.php`
- Create: `app/Http/Controllers/Tenant/ProductController.php`
- Create: `resources/js/pages/tenant/products/index.tsx`
- Modify: `resources/js/components/tenant/tenant-sidebar.tsx`
- Create: `tests/Feature/Tenant/ProductTest.php`

- [ ] **Step 1: Write the failing index test**

Create `tests/Feature/Tenant/ProductTest.php`:

```php
<?php

use App\Actions\ProvisionTenant;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = app(ProvisionTenant::class)->handle(
        'Acme', 'acme', 'Ada', 'ada@acme.test', 'password123',
    );
});

it('redirects a guest from the products page to the tenant login', function () {
    $this->get('/acme/products')
        ->assertRedirect(route('tenant.login', ['tenant' => 'acme']));
});

it('lists a tenant’s products with category/supplier options, paginated', function () {
    $this->tenant->run(function () {
        $category = Category::create(['name' => 'Widgets']);
        $supplier = Supplier::create(['name' => 'Acme Supply']);
        Product::create([
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
            'category_id' => $category->id, 'supplier_id' => $supplier->id,
        ]);
        Product::create(['name' => 'Widget B', 'sku' => 'P-002', 'unit' => 'pcs']);
    });

    loginAsAcmeUser();

    $this->get('/acme/products?per_page=10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/index')
            ->has('products.data', 2)
            ->where('products.total', 2)
            ->where('filters.per_page', 10)
            ->has('categories', 1)
            ->has('suppliers', 1)
        );
});

it('searches products by name, sku or barcode', function () {
    $this->tenant->run(function () {
        Product::create(['name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs', 'barcode' => '999']);
        Product::create(['name' => 'Gadget B', 'sku' => 'P-002', 'unit' => 'pcs']);
    });

    loginAsAcmeUser();

    $this->get('/acme/products?search=999')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'P-001')
        );
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=ProductTest --compact`
Expected: FAIL — no `products` route / `tenant/products/index` page.

- [ ] **Step 3: Register the route**

In `routes/tenant.php`, add the import near the other catalog controllers:

```php
use App\Http\Controllers\Tenant\ProductController;
```

And add inside the `// Catalog` block (after `raw-materials`):

```php
            Route::resource('products', ProductController::class)
                ->only(['index', 'store', 'update', 'destroy']);
```

- [ ] **Step 4: Create the controller (index only for now)**

Create `app/Http/Controllers/Tenant/ProductController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController
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

        $products = Product::query()
            ->with(['category', 'supplier'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $group) use ($search): void {
                    $group->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'description' => $product->description,
                'image_url' => $product->image_url,
                'category_id' => $product->category_id,
                'supplier_id' => $product->supplier_id,
                'category' => $product->category?->name,
                'supplier' => $product->supplier?->name,
                'min_stock' => $product->min_stock,
                'unit' => $product->unit,
                'created_at' => $product->created_at,
            ]);

        return Inertia::render('tenant/products/index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
```

- [ ] **Step 5: Create the products page**

Create `resources/js/pages/tenant/products/index.tsx`:

```tsx
import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ImageIcon,
    LoaderCircle,
    MoreHorizontal,
    Package,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Combobox } from '@/components/combobox';
import { DataTable, type Paginator } from '@/components/data-table';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import TenantLayout from '@/layouts/tenant-layout';

type Product = {
    id: number;
    name: string;
    sku: string;
    barcode: string | null;
    description: string | null;
    image_url: string | null;
    category_id: number | null;
    supplier_id: number | null;
    category: string | null;
    supplier: string | null;
    min_stock: number;
    unit: string;
    created_at: string;
};

type Option = { id: number; name: string };

type PageProps = {
    products: Paginator<Product>;
    filters: { search: string; per_page: number };
    categories: Option[];
    suppliers: Option[];
    tenant: { slug: string; name: string };
    flash: { success: string | null };
};

function flashToast(page: { props: unknown }): void {
    const message = (page.props as { flash?: { success?: string | null } })
        .flash?.success;
    if (message) {
        toast.success(message);
    }
}

export default function ProductsIndex() {
    const page = usePage();
    const { products, filters, categories, suppliers, tenant } =
        page.props as unknown as PageProps;
    const base = `/${tenant.slug}/products`;

    const categoryOptions = categories.map((c) => ({
        value: String(c.id),
        label: c.name,
    }));
    const supplierOptions = suppliers.map((s) => ({
        value: String(s.id),
        label: s.name,
    }));

    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Product | null>(null);
    const [name, setName] = useState('');
    const [sku, setSku] = useState('');
    const [barcode, setBarcode] = useState('');
    const [unit, setUnit] = useState('');
    const [minStock, setMinStock] = useState('0');
    const [categoryId, setCategoryId] = useState('');
    const [supplierId, setSupplierId] = useState('');
    const [description, setDescription] = useState('');
    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const [removeImage, setRemoveImage] = useState(false);
    const [deleting, setDeleting] = useState<Product | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const resetForm = () => {
        setName('');
        setSku('');
        setBarcode('');
        setUnit('');
        setMinStock('0');
        setCategoryId('');
        setSupplierId('');
        setDescription('');
        setImagePreview(null);
        setRemoveImage(false);
        if (fileRef.current) {
            fileRef.current.value = '';
        }
    };

    const openCreate = () => {
        setEditing(null);
        resetForm();
        setFormOpen(true);
    };

    const openEdit = (product: Product) => {
        setEditing(product);
        setName(product.name);
        setSku(product.sku);
        setBarcode(product.barcode ?? '');
        setUnit(product.unit);
        setMinStock(String(product.min_stock ?? 0));
        setCategoryId(product.category_id ? String(product.category_id) : '');
        setSupplierId(product.supplier_id ? String(product.supplier_id) : '');
        setDescription(product.description ?? '');
        setImagePreview(product.image_url);
        setRemoveImage(false);
        if (fileRef.current) {
            fileRef.current.value = '';
        }
        setFormOpen(true);
    };

    const onImageChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (file) {
            setImagePreview(URL.createObjectURL(file));
            setRemoveImage(false);
        }
    };

    const clearImage = () => {
        setImagePreview(null);
        setRemoveImage(true);
        if (fileRef.current) {
            fileRef.current.value = '';
        }
    };

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }
        router.delete(`${base}/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: (deleted) => {
                setDeleting(null);
                flashToast(deleted);
            },
        });
    };

    const columns: ColumnDef<Product>[] = [
        {
            id: 'image',
            header: () => <span className="sr-only">Image</span>,
            meta: { className: 'w-14' },
            cell: ({ row }) =>
                row.original.image_url ? (
                    <img
                        src={row.original.image_url}
                        alt=""
                        className="size-10 rounded-md border object-cover"
                    />
                ) : (
                    <span className="grid size-10 place-items-center rounded-md bg-secondary text-muted-foreground">
                        <ImageIcon className="size-4" />
                    </span>
                ),
        },
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) => (
                <span className="font-medium text-foreground">
                    {row.original.name}
                </span>
            ),
        },
        {
            accessorKey: 'sku',
            header: 'SKU',
            cell: ({ row }) => (
                <span className="font-mono text-muted-foreground text-xs">
                    {row.original.sku}
                </span>
            ),
        },
        {
            accessorKey: 'category',
            header: 'Category',
            cell: ({ row }) => row.original.category ?? '—',
            meta: { className: 'hidden text-muted-foreground lg:table-cell' },
        },
        {
            accessorKey: 'supplier',
            header: 'Supplier',
            cell: ({ row }) => row.original.supplier ?? '—',
            meta: { className: 'hidden text-muted-foreground lg:table-cell' },
        },
        {
            accessorKey: 'min_stock',
            header: 'Min stock',
            cell: ({ row }) => row.original.min_stock.toLocaleString(),
            meta: {
                className: 'text-right text-muted-foreground tabular-nums',
            },
        },
        {
            accessorKey: 'unit',
            header: 'Unit',
            cell: ({ row }) => row.original.unit,
            meta: { className: 'hidden text-muted-foreground md:table-cell' },
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            meta: { className: 'text-right' },
            cell: ({ row }) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label={`Actions for ${row.original.name}`}
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onSelect={() => openEdit(row.original)}>
                            <Pencil className="size-4" />
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => setDeleting(row.original)}
                        >
                            <Trash2 className="size-4" />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <TenantLayout
            breadcrumbs={[
                { title: 'Dashboard', href: `/${tenant.slug}/dashboard` },
                { title: 'Products', href: base },
            ]}
        >
            <Head title="Products" />

            <div className="flex flex-col gap-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    Products
                </h1>
                <p className="text-muted-foreground text-sm">
                    Manage the finished goods in your catalog.
                </p>
            </div>

            <DataTable
                columns={columns}
                paginator={products}
                filters={filters}
                baseUrl={base}
                only={['products', 'filters']}
                getRowId={(product) => String(product.id)}
                title="Products"
                searchPlaceholder="Search name, SKU or barcode…"
                toolbar={
                    <Button onClick={openCreate} className="shrink-0">
                        <Plus className="size-4" />
                        New product
                    </Button>
                }
                emptyState={
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <span className="grid size-12 place-items-center rounded-xl bg-secondary text-foreground">
                                <Package className="size-6" />
                            </span>
                            <div className="space-y-1">
                                <h3 className="font-semibold text-lg">
                                    No products yet
                                </h3>
                                <p className="mx-auto max-w-sm text-muted-foreground text-sm">
                                    Add your first product to start building your
                                    catalog.
                                </p>
                            </div>
                            <Button onClick={openCreate}>
                                <Plus className="size-4" />
                                New product
                            </Button>
                        </CardContent>
                    </Card>
                }
            />

            {/* Create / edit dialog */}
            <Dialog
                open={formOpen}
                onOpenChange={(next) => {
                    if (!next) {
                        setFormOpen(false);
                    }
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit product' : 'New product'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Update this product.'
                                : 'Add a product to your catalog.'}
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        key={editing?.id ?? 'new'}
                        action={editing ? `${base}/${editing.id}` : base}
                        method={editing ? 'put' : 'post'}
                        disableWhileProcessing
                        onSuccess={(saved) => {
                            setFormOpen(false);
                            flashToast(saved);
                        }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                {/* Hidden inputs mirror combobox / remove state */}
                                <input
                                    type="hidden"
                                    name="category_id"
                                    value={categoryId}
                                />
                                <input
                                    type="hidden"
                                    name="supplier_id"
                                    value={supplierId}
                                />
                                {removeImage && (
                                    <input
                                        type="hidden"
                                        name="remove_image"
                                        value="1"
                                    />
                                )}

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            value={name}
                                            onChange={(e) =>
                                                setName(e.target.value)
                                            }
                                            required
                                            autoFocus
                                            placeholder="e.g. Widget A"
                                            aria-invalid={!!errors.name}
                                            aria-describedby={
                                                errors.name
                                                    ? 'name-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="name-error"
                                            role="alert"
                                            message={errors.name}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="sku">SKU</Label>
                                        <Input
                                            id="sku"
                                            name="sku"
                                            value={sku}
                                            onChange={(e) =>
                                                setSku(e.target.value)
                                            }
                                            required
                                            placeholder="e.g. P-001"
                                            aria-invalid={!!errors.sku}
                                            aria-describedby={
                                                errors.sku
                                                    ? 'sku-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="sku-error"
                                            role="alert"
                                            message={errors.sku}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="barcode">Barcode</Label>
                                        <Input
                                            id="barcode"
                                            name="barcode"
                                            value={barcode}
                                            onChange={(e) =>
                                                setBarcode(e.target.value)
                                            }
                                            placeholder="Optional"
                                            aria-invalid={!!errors.barcode}
                                            aria-describedby={
                                                errors.barcode
                                                    ? 'barcode-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="barcode-error"
                                            role="alert"
                                            message={errors.barcode}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="unit">Unit</Label>
                                        <Input
                                            id="unit"
                                            name="unit"
                                            value={unit}
                                            onChange={(e) =>
                                                setUnit(e.target.value)
                                            }
                                            required
                                            placeholder="e.g. pcs"
                                            aria-invalid={!!errors.unit}
                                            aria-describedby={
                                                errors.unit
                                                    ? 'unit-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="unit-error"
                                            role="alert"
                                            message={errors.unit}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="category">
                                            Category
                                        </Label>
                                        <Combobox
                                            id="category"
                                            options={categoryOptions}
                                            value={categoryId}
                                            onChange={setCategoryId}
                                            placeholder="Select category"
                                            searchPlaceholder="Search categories…"
                                            emptyText="No categories."
                                            invalid={!!errors.category_id}
                                            describedBy={
                                                errors.category_id
                                                    ? 'category-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="category-error"
                                            role="alert"
                                            message={errors.category_id}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="supplier">
                                            Supplier
                                        </Label>
                                        <Combobox
                                            id="supplier"
                                            options={supplierOptions}
                                            value={supplierId}
                                            onChange={setSupplierId}
                                            placeholder="Select supplier"
                                            searchPlaceholder="Search suppliers…"
                                            emptyText="No suppliers."
                                            invalid={!!errors.supplier_id}
                                            describedBy={
                                                errors.supplier_id
                                                    ? 'supplier-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="supplier-error"
                                            role="alert"
                                            message={errors.supplier_id}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="min_stock">
                                            Min stock
                                        </Label>
                                        <Input
                                            id="min_stock"
                                            name="min_stock"
                                            type="number"
                                            min={0}
                                            step="1"
                                            value={minStock}
                                            onChange={(e) =>
                                                setMinStock(e.target.value)
                                            }
                                            placeholder="0"
                                            aria-invalid={!!errors.min_stock}
                                            aria-describedby={
                                                errors.min_stock
                                                    ? 'min_stock-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="min_stock-error"
                                            role="alert"
                                            message={errors.min_stock}
                                        />
                                    </div>
                                </div>

                                {/* Image */}
                                <div className="space-y-2">
                                    <Label htmlFor="image">Image</Label>
                                    <div className="flex items-center gap-3">
                                        {imagePreview ? (
                                            <img
                                                src={imagePreview}
                                                alt=""
                                                className="size-16 rounded-md border object-cover"
                                            />
                                        ) : (
                                            <span className="grid size-16 place-items-center rounded-md border border-dashed text-muted-foreground">
                                                <ImageIcon className="size-5" />
                                            </span>
                                        )}
                                        <div className="flex flex-col gap-2">
                                            <Input
                                                ref={fileRef}
                                                id="image"
                                                name="image"
                                                type="file"
                                                accept="image/png,image/jpeg,image/webp"
                                                onChange={onImageChange}
                                                aria-invalid={!!errors.image}
                                                aria-describedby={
                                                    errors.image
                                                        ? 'image-error'
                                                        : undefined
                                                }
                                            />
                                            {imagePreview && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="w-fit"
                                                    onClick={clearImage}
                                                >
                                                    <X className="size-4" />
                                                    Remove image
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                    <InputError
                                        id="image-error"
                                        role="alert"
                                        message={errors.image}
                                    />
                                </div>

                                {/* Description */}
                                <div className="space-y-2">
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        value={description}
                                        onChange={(e) =>
                                            setDescription(e.target.value)
                                        }
                                        rows={3}
                                        placeholder="Optional notes about this product."
                                        aria-invalid={!!errors.description}
                                        aria-describedby={
                                            errors.description
                                                ? 'description-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="description-error"
                                        role="alert"
                                        message={errors.description}
                                    />
                                </div>

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            disabled={processing}
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <>
                                                <LoaderCircle className="size-4 animate-spin" />
                                                Saving…
                                            </>
                                        ) : editing ? (
                                            'Save changes'
                                        ) : (
                                            'Create product'
                                        )}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            {/* Delete confirmation */}
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
                        <DialogTitle>Delete product</DialogTitle>
                        <DialogDescription>
                            Remove “{deleting?.name}” from your catalog? This can
                            be restored later.
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
        </TenantLayout>
    );
}
```

- [ ] **Step 6: Add the sidebar nav item**

In `resources/js/components/tenant/tenant-sidebar.tsx`, add `Package` to the lucide import and append a nav item after "Raw materials":

```tsx
        {
            title: 'Products',
            href: `/${slug}/products`,
            icon: Package,
        },
```

(Update the import line to: `import { Boxes, Contact, FolderTree, LayoutGrid, Package, Truck } from 'lucide-react';`)

- [ ] **Step 7: Run the index test — expect PASS**

Run: `php artisan test --filter=ProductTest --compact`
Expected: the 3 index/search/guard tests PASS.

- [ ] **Step 8: Verify frontend**

Run: `bun run check && bun run types:check`
Expected: 0 errors.

- [ ] **Step 9: Commit**

```bash
git add routes/tenant.php app/Http/Controllers/Tenant/ProductController.php resources/js/pages/tenant/products resources/js/components/tenant/tenant-sidebar.tsx tests/Feature/Tenant/ProductTest.php
git commit -m "feat(products): list page, index controller, route and nav"
```

---

## Task 4: Create — request, store, validation

**Files:**
- Create: `app/Http/Requests/Tenant/ProductRequest.php`
- Modify: `app/Http/Controllers/Tenant/ProductController.php`
- Modify: `tests/Feature/Tenant/ProductTest.php`

- [ ] **Step 1: Write the failing create/validation tests**

Append to `tests/Feature/Tenant/ProductTest.php`:

```php
it('creates a product with category, supplier and defaults min_stock to 0', function () {
    [$categoryId, $supplierId] = $this->tenant->run(function () {
        return [
            Category::create(['name' => 'Widgets'])->id,
            Supplier::create(['name' => 'Acme Supply'])->id,
        ];
    });

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', [
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
            'category_id' => $categoryId, 'supplier_id' => $supplierId,
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($categoryId, $supplierId) {
        $product = Product::firstWhere('sku', 'P-001');
        expect($product)->not->toBeNull()
            ->and($product->min_stock)->toBe(0)
            ->and($product->category_id)->toBe($categoryId)
            ->and($product->supplier_id)->toBe($supplierId)
            ->and($product->image)->toBeNull();
    });
});

it('requires name, sku and unit', function () {
    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', [])
        ->assertRedirect('/acme/products')
        ->assertSessionHasErrors(['name', 'sku', 'unit']);
});

it('rejects a duplicate sku', function () {
    $this->tenant->run(fn () => Product::create([
        'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
    ]));

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', ['name' => 'Other', 'sku' => 'P-001', 'unit' => 'pcs'])
        ->assertRedirect('/acme/products')
        ->assertSessionHasErrors('sku');
});

it('rejects a trashed category or supplier', function () {
    $categoryId = $this->tenant->run(function () {
        $category = Category::create(['name' => 'Widgets']);
        $category->delete();

        return $category->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', [
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
            'category_id' => $categoryId,
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHasErrors('category_id');
});

it('rejects a non-integer min_stock', function () {
    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', [
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
            'min_stock' => '1.5',
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHasErrors('min_stock');
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --filter=ProductTest --compact`
Expected: the new tests FAIL (store returns 405/route method missing).

- [ ] **Step 3: Create the FormRequest**

Create `app/Http/Requests/Tenant/ProductRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is already gated by auth:web; belt-and-suspenders.
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
        $product = $this->route('product');
        $ignoreId = $product instanceof Product ? $product->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required', 'string', 'max:100',
                Rule::unique('products', 'sku')->ignore($ignoreId),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
            ],
            'supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->whereNull('deleted_at'),
            ],
            'min_stock' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Add `store` to the controller**

Add these imports to `ProductController.php`:

```php
use App\Http\Requests\Tenant\ProductRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
```

Add the method after `index`:

```php
    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['remove_image']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($data['image']);
        }

        Product::create($data);

        return back()->with('success', 'Product created.');
    }
```

- [ ] **Step 5: Run the tests — expect PASS**

Run: `php artisan test --filter=ProductTest --compact`
Expected: all create/validation tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Tenant/ProductRequest.php app/Http/Controllers/Tenant/ProductController.php tests/Feature/Tenant/ProductTest.php
git commit -m "feat(products): create + validation (sku unique, trashed FK, int min_stock)"
```

---

## Task 5: Image upload + update (with replace/remove) + storage cleanup

**Files:**
- Modify: `tests/TestCase.php`
- Modify: `app/Http/Controllers/Tenant/ProductController.php`
- Modify: `tests/Feature/Tenant/ProductTest.php`

- [ ] **Step 1: Clean up test tenants' storage dirs**

`Storage::fake` is defeated by the tenancy filesystem bootstrapper (it rewrites the `public` disk root to the tenant-suffixed real path on every bootstrap). So uploaded files really land in `storage/tenant<slug>/…`. Delete them in `purgeTenants()` — scoped to tenants in the TEST central DB, so dev/prod tenant storage is never touched.

In `tests/TestCase.php`, add the import:

```php
use Illuminate\Support\Facades\File;
```

Then in `purgeTenants()`, replace the tenant-freeing block:

```php
        // Free slugs via a mass query-builder delete (no model events / no DDL).
        if (class_exists(Tenant::class)) {
            Tenant::withTrashed()->forceDelete();
        }
```

with:

```php
        // Free slugs via a mass query-builder delete (no model events / no DDL).
        if (class_exists(Tenant::class)) {
            // Remove any files uploads left in each test tenant's suffixed
            // storage dir (storage/tenant<key>). Scoped to tenants in the TEST
            // central DB, so dev/prod tenant storage is never touched.
            $suffixBase = (string) config('tenancy.filesystem.suffix_base', 'tenant');
            foreach (Tenant::withTrashed()->pluck('id') as $key) {
                File::deleteDirectory(storage_path($suffixBase.$key));
            }

            Tenant::withTrashed()->forceDelete();
        }
```

- [ ] **Step 2: Write the failing image + update tests**

Append to `tests/Feature/Tenant/ProductTest.php` (add `use Illuminate\Http\UploadedFile;` and `use Illuminate\Support\Facades\Storage;` at the top of the file):

```php
it('stores an uploaded image on the tenant public disk', function () {
    loginAsAcmeUser();

    $this->from('/acme/products')
        ->post('/acme/products', [
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs',
            'image' => UploadedFile::fake()->image('widget.jpg', 200, 200),
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHas('success');

    $this->tenant->run(function () {
        $product = Product::firstWhere('sku', 'P-001');
        expect($product->image)->not->toBeNull()
            ->and(str_starts_with($product->image, 'products/'))->toBeTrue()
            ->and(Storage::disk('public')->exists($product->image))->toBeTrue();
    });
});

it('updates a product and replaces its image', function () {
    $id = $this->tenant->run(fn () => Product::create([
        'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
    ])->id);

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$id}", [
            'name' => 'Widget A', 'sku' => 'P-001', 'unit' => 'pcs', 'min_stock' => 5,
            'image' => UploadedFile::fake()->image('new.png', 150, 150),
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        $product = Product::find($id);
        expect($product->name)->toBe('Widget A')
            ->and($product->min_stock)->toBe(5)
            ->and($product->image)->not->toBeNull()
            ->and(Storage::disk('public')->exists($product->image))->toBeTrue();
    });
});

it('removes a product image when remove_image is set', function () {
    $id = $this->tenant->run(function () {
        return Product::create([
            'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
            'image' => 'products/existing.jpg',
        ])->id;
    });

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->put("/acme/products/{$id}", [
            'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs', 'min_stock' => 0,
            'remove_image' => '1',
        ])
        ->assertRedirect('/acme/products')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(Product::find($id)->image)->toBeNull();
    });
});
```

- [ ] **Step 3: Run to verify they fail**

Run: `php artisan test --filter=ProductTest --compact`
Expected: the image-store test may pass partially, but the update tests FAIL (`update` method missing → 405).

- [ ] **Step 4: Add `update` to the controller**

Add after `store`:

```php
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $removeImage = (bool) ($data['remove_image'] ?? false);
        unset($data['remove_image']);

        if ($request->hasFile('image')) {
            $this->deleteImage($product);
            $data['image'] = $request->file('image')->store('products', 'public');
        } elseif ($removeImage) {
            $this->deleteImage($product);
            $data['image'] = null;
        } else {
            unset($data['image']);
        }

        $product->update($data);

        return back()->with('success', 'Product updated.');
    }

    private function deleteImage(Product $product): void
    {
        if ($product->image !== null) {
            Storage::disk('public')->delete($product->image);
        }
    }
```

- [ ] **Step 5: Run the tests — expect PASS**

Run: `php artisan test --filter=ProductTest --compact`
Expected: all image + update tests PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/TestCase.php app/Http/Controllers/Tenant/ProductController.php tests/Feature/Tenant/ProductTest.php
git commit -m "feat(products): image upload, replace/remove on update; clean test storage"
```

---

## Task 6: Delete (soft)

**Files:**
- Modify: `app/Http/Controllers/Tenant/ProductController.php`
- Modify: `tests/Feature/Tenant/ProductTest.php`

- [ ] **Step 1: Write the failing delete test**

Append to `tests/Feature/Tenant/ProductTest.php`:

```php
it('soft-deletes a product', function () {
    $id = $this->tenant->run(fn () => Product::create([
        'name' => 'Widget', 'sku' => 'P-001', 'unit' => 'pcs',
    ])->id);

    loginAsAcmeUser();

    $this->from('/acme/products')
        ->delete("/acme/products/{$id}")
        ->assertRedirect('/acme/products')
        ->assertSessionHas('success');

    $this->tenant->run(function () use ($id) {
        expect(Product::find($id))->toBeNull()
            ->and(Product::withTrashed()->find($id))->not->toBeNull();
    });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=ProductTest --compact`
Expected: FAIL (`destroy` missing → 405).

- [ ] **Step 3: Add `destroy` to the controller**

Add after `update`:

```php
    public function destroy(Product $product): RedirectResponse
    {
        // Soft delete; the image file is kept so a restore stays intact.
        $product->delete();

        return back()->with('success', 'Product deleted.');
    }
```

- [ ] **Step 4: Run the test — expect PASS**

Run: `php artisan test --filter=ProductTest --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Tenant/ProductController.php tests/Feature/Tenant/ProductTest.php
git commit -m "feat(products): soft-delete"
```

---

## Task 7: Full verification, local migrate + seed

**Files:** none (verification + local dev DB only)

- [ ] **Step 1: Full quality gate**

Run each; all must be clean:

```bash
vendor/bin/pint --dirty
bun run check
bun run check:ci
bun run types:check
php artisan test --compact
```

Expected: Pint clean, Biome 0 errors, types pass, **all** tests green (existing 102 + the new ProductTest cases).

- [ ] **Step 2: Build the frontend**

Run: `bun run build`
Expected: succeeds (validates the products page + combobox compile in production).

- [ ] **Step 3: Migrate the local dev DBs**

Per the standing rule (tests use throwaway DBs, dev DBs are not auto-migrated):

```bash
php artisan migrate --force
php artisan tenants:migrate
```

Expected: the `products` table is created in the local tenant DB(s) (e.g. `demo`).

- [ ] **Step 4: Seed a couple of demo products**

So the list renders populated (adjust category/supplier lookups to whatever exists in `demo`):

```bash
php artisan tinker --execute="
App\Models\Tenant::find('demo')->run(function () {
    \$cat = App\Models\Category::firstOrCreate(['name' => 'Widgets']);
    \$sup = App\Models\Supplier::firstOrCreate(['name' => 'Acme Supply']);
    App\Models\Product::firstOrCreate(['sku' => 'P-001'], [
        'name' => 'Widget A', 'unit' => 'pcs', 'min_stock' => 10,
        'category_id' => \$cat->id, 'supplier_id' => \$sup->id,
    ]);
    App\Models\Product::firstOrCreate(['sku' => 'P-002'], [
        'name' => 'Widget B', 'unit' => 'pcs', 'min_stock' => 5,
    ]);
});
"
```

Expected: two demo products in the `demo` tenant.

- [ ] **Step 5: Manual smoke (optional but recommended)**

Visit `/demo/products` in the browser: list shows the thumbnail column + rows, "New product" opens the dialog, the category/supplier comboboxes filter, and an image upload previews. Confirm dark mode looks right.

- [ ] **Step 6: Final commit (if pint/biome reformatted anything)**

```bash
git add -A
git commit -m "chore(products): formatting + verification" || echo "nothing to commit"
```

---

## Self-Review

**Spec coverage:**
- §4 migration/model → Task 2. §5 validation → Task 4 (+ image rules used in Task 5). §6 controller/routes → Tasks 3–6. §7 image handling (store/serve/replace/remove/soft-delete keeps file) → Tasks 2 (accessor), 5 (store/update). §8 frontend (DataTable + dialog + combobox + textarea + thumbnail) → Tasks 1, 3. §9 tests → Tasks 3–6. §10 post-build (quality gate, local migrate, seed, nav) → Tasks 3 (nav), 7. ✅ all covered.

**Placeholder scan:** none — every step has full code/commands.

**Type consistency:** `ProductRequest`, `Product`, `image_url`, `category_id`/`supplier_id`, `remove_image`, `min_stock:int`, `store('products','public')`, and the page prop shape (`products`, `filters`, `categories`, `suppliers`) are consistent across controller, request, page, and tests. Combobox prop names (`options`, `value`, `onChange`, `invalid`, `describedBy`) match between `combobox.tsx` and the page. `deleteImage()` private helper is defined once (Task 5) and used in `update` only.
