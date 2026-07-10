# laravel-data — Products pilot (typed DTOs + generated TS)

**Date:** 2026-07-10
**Status:** Approved (design)
**Depends on:** the Products module (shipped).

## 1. Purpose

Kill the backend↔frontend data-shape duplication: today each list controller hand-maps a model
to an array (`->through(fn => [...])`) **and** each page re-declares the matching TS type by hand,
so the two drift. Adopt **`spatie/laravel-data`** (define the shape once as a Data class that
serializes the Inertia payload) + **`spatie/laravel-typescript-transformer`** (auto-generate the
matching TypeScript). This is a **pilot on Products only**, to prove the pattern before rolling it
out to the other resources.

## 2. Scope

**In scope**
- Install + configure `laravel-data` (runtime) and `laravel-typescript-transformer` (dev).
- `ProductData` + `OptionData` (the category/supplier picker shape).
- Convert `ProductController@index` to emit those Data objects.
- Generate TS types (gitignored) and consume them in `products/index.tsx`.
- Wire a `types:generate` step into the JS scripts.

**Out of scope (deliberate — deferred to a follow-up)**
- Other resources (suppliers, customers, raw-materials, categories, admin tenants).
- Using Data for **validation** — the existing `FormRequest`s stay untouched.
- `store`/`update`/`destroy` — unchanged.

## 3. Decisions (settled in brainstorming)

| Decision | Choice |
| --- | --- |
| Scope | Pilot on **Products only** |
| What Data replaces | **Output/props only** — keep `ProductRequest` for validation |
| Generated TS | **Generated at build, git-ignored** (not committed) |
| TS output style | `type` aliases (matches the codebase; no `interface`) |
| TS delivery | Ambient `.d.ts` namespace (`declare namespace App.Data { … }`) via the transformer's default `TypeDefinitionWriter` → referenced as `App.Data.ProductData` |
| Property naming | **snake_case** properties on the Data classes so the JSON + generated TS keys are byte-identical to today's payload (`image_url`, `category_id`, `min_stock`, `created_at`) — the 122 tests assert those keys. (Idiomatic camelCase + a global `SnakeCaseMapper` is a later refinement.) |

## 4. Packages & config

- `composer require spatie/laravel-data`
- `composer require --dev spatie/laravel-typescript-transformer`
- Publish both configs (`config/data.php`, `config/typescript-transformer.php`).
- `config/typescript-transformer.php`:
  - `auto_discover_types` → include `app_path('Data')`.
  - `transformers` → keep the default set **plus** laravel-data's
    `Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer`.
  - `output_file` → `resources/js/types/generated.d.ts`.
  - `writer` → the default `TypeDefinitionWriter` (ambient `declare namespace App.Data`).
- `tsconfig.json` already includes `resources/js/**/*.d.ts`, so the generated file is picked up
  with no config change.

## 5. Backend

### `app/Data/ProductData.php`

```php
namespace App\Data;

use App\Models\Product;
use Spatie\LaravelData\Data;

class ProductData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $sku,
        public ?string $barcode,
        public ?string $description,
        public ?string $image_url,
        public ?int $category_id,
        public ?int $supplier_id,
        public ?string $category,   // category name (or null)
        public ?string $supplier,   // supplier name (or null)
        public int $min_stock,
        public string $unit,
        public ?string $created_at, // ISO 8601 string
    ) {}

    public static function fromProduct(Product $product): self
    {
        return new self(
            id: $product->id,
            name: $product->name,
            sku: $product->sku,
            barcode: $product->barcode,
            description: $product->description,
            image_url: $product->image_url,
            category_id: $product->category_id,
            supplier_id: $product->supplier_id,
            category: $product->category?->name,
            supplier: $product->supplier?->name,
            min_stock: $product->min_stock,
            unit: $product->unit,
            created_at: $product->created_at?->toISOString(),
        );
    }
}
```

- The static `fromProduct` factory is auto-dispatched by `ProductData::from($product)`; it's where
  the computed bits (`image_url` accessor, `category?->name`, `supplier?->name`,
  `created_at->toISOString()`) live — one place, reused anywhere a product is serialized.
- snake_case property names ⇒ the serialized JSON keys and the generated TS keys match today's
  payload exactly.

### `app/Data/OptionData.php`

```php
namespace App\Data;

use Spatie\LaravelData\Data;

class OptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
```

### `ProductController@index`

- `->through(fn (Product $product): ProductData => ProductData::from($product))` in place of the
  hand-mapped array.
- `'categories' => OptionData::collect(Category::orderBy('name')->get())` and the same for
  `suppliers` (drop the `->get(['id','name'])` column list — `OptionData` only reads id + name).
- Everything else (`filters`, `Inertia::render('tenant/products/index', …)`) unchanged.

The paginator returned by `->through(...)` now holds `ProductData` instances; Inertia serializes
each to the identical JSON, so `products.data`, `products.total`, `filters.per_page`, etc. are
unchanged.

## 6. Frontend — `resources/js/pages/tenant/products/index.tsx`

Replace the hand-written type **bodies** with aliases to the generated ambient types; everything
else in the page (columns, dialog state, `PageProps`) is untouched:

```tsx
type Product = App.Data.ProductData;
type Option = App.Data.OptionData;
```

- `App.Data.*` is globally available from the generated `.d.ts` (no import).
- `PageProps`, the `ColumnDef<Product>[]`, and all `row.original.*` usages stay as-is.

### Scripts / ignore

- `.gitignore` → add `resources/js/types/generated.d.ts`.
- `package.json`:
  - add `"types:generate": "php artisan typescript:transform --force"`
  - `"types:check": "php artisan typescript:transform --force && tsc --noEmit"` (a fresh clone or CI
    generates the types before checking, since they're not committed)
  - `"dev": "php artisan typescript:transform --force && vite"` (so the editor has types in dev)

## 7. Testing & verification

- **No behavioral change** — the payload shape is identical, so the existing `ProductTest`
  (index/search asserting `products.data.*`, `has('categories', 1)`, etc.) and the full **122-test**
  suite must stay green. This is the primary safety check.
- `php artisan typescript:transform --force` produces `generated.d.ts` containing
  `App.Data.ProductData` + `App.Data.OptionData`.
- `bun run types:check` (which now generates first) passes — proving `products/index.tsx` type-checks
  against the generated types.
- `vendor/bin/pint --dirty`, `bun run check:ci` (0 errors), `bun run build` succeed.

## 8. Rollout note (not this cycle)

Once the pilot is green, the follow-up applies the same pattern to the other list resources
(one `*Data` class each + `->through(fn => …Data::from($m))`), reusing `OptionData`. Consider the
idiomatic camelCase-properties + global `SnakeCaseMapper` at that point.
