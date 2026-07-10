# laravel-data Products Pilot — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adopt `spatie/laravel-data` + `spatie/laravel-typescript-transformer` on the Products resource (output/props only) so the payload shape is defined once in PHP and the TypeScript type is generated — killing the backend↔frontend duplication.

**Architecture:** `ProductData`/`OptionData` Data classes replace `ProductController@index`'s hand-mapped arrays and serialize to the **identical** JSON (snake_case props). The transformer generates an ambient `resources/js/types/generated.d.ts` (git-ignored) exposing `App.Data.ProductData`; the page aliases its hand-written types to those. `FormRequest` validation is untouched.

**Tech Stack:** Laravel 13.18, `spatie/laravel-data` ^4.23, `spatie/laravel-typescript-transformer` ^3.3, Inertia v3, React 19 + TS, Pest, Bun, Biome, Pint.

**Spec:** `docs/superpowers/specs/2026-07-10-laravel-data-products-pilot-design.md`

**Safety net:** this is a behavior-preserving refactor — the existing **122 tests** (esp. `tests/Feature/Tenant/ProductTest.php`, which asserts `products.data.*`, `products.total`, `has('categories',1)`) must stay green after each backend change. No new backend tests are needed; the payload shape is the contract.

---

## File Structure

**Create:**
- `app/Data/ProductData.php` — the product list-item shape + `fromProduct()` factory
- `app/Data/OptionData.php` — `{ id, name }` picker shape
- `config/data.php`, `config/typescript-transformer.php` — published package config
- `resources/js/types/generated.d.ts` — generated, **git-ignored** (not committed)

**Modify:**
- `composer.json` / `composer.lock` — new deps
- `app/Http/Controllers/Tenant/ProductController.php` — index emits Data
- `resources/js/pages/tenant/products/index.tsx` — alias types to `App.Data.*`
- `package.json` — `types:generate` script + wire into `types:check`/`dev`
- `.gitignore` — ignore the generated types file

---

## Task 1: Install + configure the packages

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create/Modify: `config/data.php`, `config/typescript-transformer.php`

- [ ] **Step 1: Require the packages**

```bash
composer require spatie/laravel-data
composer require --dev spatie/laravel-typescript-transformer
```
Expected: `laravel-data ^4.23` in `require`, `laravel-typescript-transformer ^3.3` in `require-dev`; no version conflicts (verified via dry-run already).

- [ ] **Step 2: Publish the configs**

```bash
php artisan vendor:publish --tag=data-config
php artisan vendor:publish --provider="Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerServiceProvider"
```
Expected: `config/data.php` and `config/typescript-transformer.php` exist. (`data.php` needs no edits for this pilot; defaults are fine.)

- [ ] **Step 3: Configure the transformer**

Edit `config/typescript-transformer.php`:
- Set `auto_discover_types` to scan only the Data dir:
  ```php
  'auto_discover_types' => [app_path('Data')],
  ```
- Ensure the `transformers` array includes laravel-data's transformer (add it if absent):
  ```php
  'transformers' => [
      Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer::class,
      // ...keep any existing default transformers (enum, etc.)...
  ],
  ```
- Set the output path:
  ```php
  'output_file' => resource_path('js/types/generated.d.ts'),
  ```
- Leave `writer` at its default (`TypeScriptTransformer\Writers\TypeDefinitionWriter` — emits `declare namespace App.Data { … }`).

- [ ] **Step 4: Verify install compiles**

Run: `php artisan config:clear && php artisan about --only=environment`
Expected: no errors (the service providers boot). Then `vendor/bin/pint --test` → clean.

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock config/data.php config/typescript-transformer.php
git commit -m "chore(data): install laravel-data + typescript-transformer, configure output"
```

---

## Task 2: ProductData + OptionData

**Files:**
- Create: `app/Data/ProductData.php`, `app/Data/OptionData.php`

- [ ] **Step 1: Create `app/Data/ProductData.php`**

```php
<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Product;
use Spatie\LaravelData\Data;

/**
 * The product list-item payload. snake_case property names keep the serialized
 * JSON (and the generated TS) byte-identical to the previous hand-mapped array.
 */
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
        public ?string $category,
        public ?string $supplier,
        public int $min_stock,
        public string $unit,
        public string $created_at,
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
            created_at: $product->created_at->toISOString(),
        );
    }
}
```

- [ ] **Step 2: Create `app/Data/OptionData.php`**

```php
<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/** A `{ id, name }` option for a select/combobox (category, supplier, …). */
class OptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
```

- [ ] **Step 3: Lint + sanity-check**

Run: `vendor/bin/pint --dirty`
Expected: formatted, no errors.

Run (confirm a Data object serializes to the expected snake_case keys, using the demo tenant):
```bash
php artisan tinker --execute="
App\Models\Tenant::find('demo')->run(function () {
    \$p = App\Models\Product::with(['category','supplier'])->first();
    if (\$p) { echo json_encode(App\Data\ProductData::from(\$p)->toArray()) . PHP_EOL; }
    else { echo 'no demo product' . PHP_EOL; }
});
"
```
Expected: JSON with keys `id,name,sku,barcode,description,image_url,category_id,supplier_id,category,supplier,min_stock,unit,created_at` (snake_case), matching the current payload.

- [ ] **Step 4: Commit**

```bash
git add app/Data
git commit -m "feat(data): add ProductData + OptionData"
```

---

## Task 3: Convert `ProductController@index` to Data

**Files:**
- Modify: `app/Http/Controllers/Tenant/ProductController.php`

- [ ] **Step 1: Add imports**

Add to the `use` block:
```php
use App\Data\OptionData;
use App\Data\ProductData;
```

- [ ] **Step 2: Replace the `->through()` map**

Find the `->through(fn (Product $product): array => [ ... ])` closure in `index()` and replace the whole `->through(...)` call with:
```php
            ->through(fn (Product $product): ProductData => ProductData::from($product));
```

- [ ] **Step 3: Replace the picker payloads**

In the `Inertia::render(...)` array, replace:
```php
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
```
with:
```php
            'categories' => OptionData::collect(Category::orderBy('name')->get(['id', 'name'])),
            'suppliers' => OptionData::collect(Supplier::orderBy('name')->get(['id', 'name'])),
```

- [ ] **Step 4: Run the Products tests — payload shape must be unchanged**

Run: `php artisan test --filter=ProductTest --compact`
Expected: **16 passed** — same as before. The index/search tests assert `products.data.0.sku`, `has('products.data', N)`, `has('categories', 1)`, `has('suppliers', 1)`; these only pass if `ProductData`/`OptionData` serialize to the identical shape. If any fail on a missing/renamed key, the Data property names don't match the old array — fix the property names (must be exactly the keys listed in Task 2 Step 3).

- [ ] **Step 5: Run the full suite**

Run: `php artisan test --compact`
Expected: **122 passed** (unchanged).

- [ ] **Step 6: Lint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Tenant/ProductController.php
git commit -m "refactor(products): emit ProductData/OptionData from the index controller"
```

---

## Task 4: Generate the TS types + wire the scripts

**Files:**
- Modify: `.gitignore`, `package.json`
- Generated (not committed): `resources/js/types/generated.d.ts`

- [ ] **Step 1: Git-ignore the generated file**

Append to `.gitignore`:
```
/resources/js/types/generated.d.ts
```

- [ ] **Step 2: Generate the types + inspect them**

Run: `php artisan typescript:transform`
Expected: writes `resources/js/types/generated.d.ts`. Then inspect it:
```bash
cat resources/js/types/generated.d.ts
```
Expected content (shape, not exact formatting):
```ts
declare namespace App.Data {
    export type ProductData = {
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
    export type OptionData = {
        id: number;
        name: string;
    };
}
```
**If the file is empty or missing the Data types:** the transformer didn't discover them via `auto_discover_types`. Fix by adding the `#[TypeScript]` attribute to both Data classes (`use Spatie\TypeScriptTransformer\Attributes\TypeScript;` then `#[TypeScript]` above each class) and re-run. **If keys are camelCase** (`imageUrl`), the Data properties weren't snake_case — fix them in `app/Data/ProductData.php`. Do not proceed until the generated types match the shape above.

- [ ] **Step 3: Add the `types:generate` script + wire it in**

In `package.json` `scripts`, add/modify:
```json
        "types:generate": "php artisan typescript:transform",
        "types:check": "php artisan typescript:transform && tsc --noEmit",
        "dev": "php artisan typescript:transform && vite",
```
(Only these three lines change; leave the others.)

- [ ] **Step 4: Verify the wired script regenerates**

```bash
rm -f resources/js/types/generated.d.ts
bun run types:generate
test -f resources/js/types/generated.d.ts && echo "generated OK"
```
Expected: `generated OK`.

- [ ] **Step 5: Commit (scripts + ignore only — NOT the generated file)**

```bash
git add .gitignore package.json
git status --porcelain resources/js/types/generated.d.ts   # should print nothing (ignored)
git commit -m "build(types): generate TS from Data classes (git-ignored); wire types:generate"
```

---

## Task 5: Consume the generated types in the page

**Files:**
- Modify: `resources/js/pages/tenant/products/index.tsx`

- [ ] **Step 1: Alias the hand-written types to the generated ones**

In `resources/js/pages/tenant/products/index.tsx`, replace the whole hand-written `type Product = { … };` block and the `type Option = { id: number; name: string };` line with:
```tsx
type Product = App.Data.ProductData;
type Option = App.Data.OptionData;
```
Leave everything else — `type PageProps`, `flashToast`, the `ColumnDef<Product>[]` columns, all `row.original.*` usages, the dialog state — **unchanged** (they reference `Product`/`Option`, which now resolve to the generated shapes).

- [ ] **Step 2: Type-check (generates types first, then tsc)**

Run: `bun run types:check`
Expected: clean (exit 0). `App.Data.ProductData` resolves from the generated `.d.ts` (tsconfig already includes `resources/js/**/*.d.ts`). If tsc reports `Cannot find namespace 'App'`, the generated file wasn't produced — run `bun run types:generate` and confirm the file exists, then re-run.

- [ ] **Step 3: Biome + build**

```bash
bun run check
bun run check:ci
bun run build
```
Expected: Biome 0 errors; production build succeeds.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/tenant/products/index.tsx
git commit -m "refactor(products): use generated App.Data types on the products page"
```

---

## Task 6: Full verification

**Files:** none (verification only)

- [ ] **Step 1: Full gate**

```bash
vendor/bin/pint --test
bun run types:check
bun run check:ci
bun run build
php artisan test --compact
```
Expected: Pint clean, types clean, Biome 0 errors, build succeeds, **122 tests passed**.

- [ ] **Step 2: Confirm the payload is byte-identical (regression guard)**

Compare the Data output keys against the documented shape (demo tenant):
```bash
php artisan tinker --execute="
App\Models\Tenant::find('demo')->run(function () {
    \$p = App\Models\Product::with(['category','supplier'])->first();
    if (\$p) { echo implode(',', array_keys(App\Data\ProductData::from(\$p)->toArray())) . PHP_EOL; }
});
"
```
Expected: `id,name,sku,barcode,description,image_url,category_id,supplier_id,category,supplier,min_stock,unit,created_at`.

- [ ] **Step 3: Final commit if anything reformatted**

```bash
git add -A
git commit -m "chore(data): pilot verification" || echo "nothing to commit"
```

---

## Self-Review

**Spec coverage:** §4 packages/config → Task 1. §5 ProductData/OptionData → Task 2; controller → Task 3. §6 frontend alias + `.gitignore` + scripts → Tasks 4–5. §7 verification (122 green + types + build) → Tasks 3, 5, 6. §3 decisions (snake_case, gitignored ambient `.d.ts`, keep FormRequests) → honored throughout. ✅ covered.

**Placeholder scan:** none — every step has concrete code/commands. The two "if it doesn't work" branches (Task 4 Step 2, Task 5 Step 2) are explicit recovery instructions, not placeholders.

**Type consistency:** the 13 `ProductData` keys (Task 2) match the controller mapping (Task 3), the generated TS shape (Task 4), and the page's existing `row.original.*` usages (Task 5). `OptionData` = `{id,name}` consistently. `App.Data.ProductData`/`App.Data.OptionData` referenced identically in Tasks 4–5.
