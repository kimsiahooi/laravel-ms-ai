# Reusable Patterns & DRY Opportunities

A living catalog of duplication found across the codebase and the reusable
abstractions (hooks, components, traits, helpers) that remove it. Update the
**Status** column as items land.

> Scope note: "reusable" here means _one home, many callers_. Prefer extracting
> only genuinely repeated logic (3+ copies, or 2 copies of non-trivial logic that
> will clearly grow). Don't abstract one-offs.

## Where shared code lives

| Kind | Location | Example |
| --- | --- | --- |
| React hooks | `resources/js/hooks/` | `use-flash-toast.ts`, `use-mobile.tsx` |
| React shared components | `resources/js/components/` | `data-table.tsx`, `combobox.tsx` |
| React utilities | `resources/js/lib/` | `utils.ts`, `format.ts` |
| Laravel controller traits | `app/Http/Controllers/Concerns/` | `ResolvesPerPage` |
| Laravel request traits | `app/Http/Requests/Concerns/` _(proposed)_ | — |
| Laravel actions | `app/Actions/` | `ProvisionTenant` |

---

## Backend (Laravel)

### 1. ✅ `per_page` resolution → `ResolvesPerPage` trait  · **DONE**
**Was:** every list controller declared `private const PER_PAGE_OPTIONS = [10,25,50,100]`
and repeated the same validate-or-fallback block (6 controllers; `Central/TenantController`
had it **twice**).
**Now:** `app/Http/Controllers/Concerns/ResolvesPerPage.php` — `use ResolvesPerPage;` then
`$perPage = $this->perPage($request);`. Override `$perPageOptions` per controller if needed.
**Impact:** removed ~40 duplicated lines across 6 files.

### 2. ✅ Searchable-index query → `Searchable` model scope  · **DONE**
**Was:** the 5 catalog `index()` methods each repeated a grouped `OR LIKE` search closure
(`->when($search !== '', fn => where(group => …))`); only the columns differed.
**Now:** `app/Models/Concerns/Searchable.php` — models declare
`protected array $searchable = ['name', …]` and `use Searchable;`, and controllers call
`->search($search)`. The `->through()` map stays per-controller (genuinely per-resource).
**Impact:** removed the closure from 5 controllers (and the now-unused `Builder` import).
Added a search test per catalog resource (Category/Supplier/Customer/RawMaterial — Product
already had one) so each `$searchable` column set is verified.

### 3. ✅ Per-tenant image store/delete → `InteractsWithTenantAssets` trait  · **DONE**
**Was:** `ProductController` held `storeImage()`/`deleteImage()` + an `IMAGE_DISK` const, and
`TenantStorageController` repeated the same disk name + `tenant('id').'/'` prefix — the store and
serve sides could silently drift apart.
**Now:** `app/Http/Controllers/Concerns/InteractsWithTenantAssets.php` — `storeAsset($file,
$directory)` / `deleteAsset(?$path)` / `scopeAsset($path)` / `assetDisk()`. Parameterized by
directory, so every entity reuses it (products now → `products`; future avatars/logos → their own
dir). Both controllers `use` it, so the `assets` disk + slug-scoping live in one place.

### 4. ✅ `FormRequest::authorize()` boilerplate → `TenantFormRequest` base  · **DONE**
`app/Http/Requests/Tenant/TenantFormRequest.php` — abstract base extending `FormRequest` with the
shared `authorize(): bool { return $this->user() !== null; }`. The 5 tenant requests
(Category / Customer / Supplier / RawMaterial / Product) now `extends TenantFormRequest` and declare
only `rules()`. (Central `StoreTenantRequest` stays independent — it's the only central request.)

### 5. ✅ `min_stock` blank→0 coercion → `NormalizesNumericInput` trait  · **DONE**
`app/Http/Requests/Concerns/NormalizesNumericInput.php` — `defaultBlankToZero(string $field)`
merges a blank (`null`/`''`) field to `0` before validation. `RawMaterialRequest` and
`ProductRequest` `use` it and call `$this->defaultBlankToZero('min_stock')` in
`prepareForValidation()`. Behaviour unchanged (existing coercion tests stay green).

---

## Frontend (React)

### 1. ✅ Flash toasts: one global convention → `RespondsWithToast` + `useFlashToast`  · **DONE**
The codebase had two flash conventions; it now has **one**. Convention A (per-page `flashToast` +
`->with('success')` + a shared `flash.success` prop) is **deleted**; everything uses the reactive
global path:

- **Backend:** `app/Http/Controllers/Concerns/RespondsWithToast.php` — `$this->toast('Saved.')`
  (`$type` defaults to `success`) calls `Inertia::flash('toast', ['type' => …, 'message' => …])`.
  All 6 mutating controllers (5 catalog + `Central/TenantController`) use it; no controller uses
  `->with('success')`.
- **Frontend:** the `useFlashToast()` hook (mounted once in `<Toaster>`) renders every flash — the
  7 pages have **zero** toast wiring. `@/lib/flash.ts` is deleted; `HandleInertiaRequests` no longer
  shares `flash.success`; `FlashSuccess` / the `flash` page prop are gone from `@/types/page`.
- **Tests:** a `TestResponse::assertToast('Saved.')` macro (registered in `tests/TestCase.php`)
  replaced the 22 `assertSessionHas('success')` assertions — exact message for the catalog,
  message-agnostic `assertToast()` for the central (interpolated) messages.

Verified: 122 Pest tests, biome/types/build clean, and a **browser smoke** (create + delete on
`/demo/raw-materials` → both toasts fire through the global hook).

### 2. ✅ Resource create/edit dialog → `useResourceDialog` + `<ResourceFormDialog>`  · **DONE**
`resources/js/hooks/use-resource-dialog.ts` — `useResourceDialog<T>({ onCreate, onEdit })` owns
`open`/`editing`/`openCreate`/`openEdit`/`close`/`onOpenChange`; the page passes field
reset/fill callbacks. `resources/js/components/resource-form-dialog.tsx` — `<ResourceFormDialog
open onOpenChange editing entityLabel baseUrl onSuccess>{({ errors }) => …fields…}</…>` renders
the Dialog + header + Inertia `<Form>` + Cancel/submit footer; callers supply only the fields.
Applied to all 5 catalog pages. Verified in a real browser (create / edit-prefill / delete, and
the combobox-in-dialog still selects).

### 2b. ✅ Delete flow → `useDelete` + `<ConfirmDeleteDialog>`  · **DONE**
`resources/js/hooks/use-delete.ts` — `useDelete<T>({ baseUrl, onDeleted })` owns `deleting` +
`request`/`cancel`/`confirm` (the `router.delete`); `onDeleted` is the page's existing
`flashToast` (**toast untouched**). `resources/js/components/confirm-delete-dialog.tsx` —
`<ConfirmDeleteDialog item onOpenChange onConfirm title description />`. Applied to all 5 catalog
pages (raw-materials 403→304, categories 329→242, suppliers/customers 437→336, products 660→544).

### 3. ✅ `ComboboxField` (Label + Combobox + InputError) → component  · **DONE**
`resources/js/components/combobox-field.tsx` — `<ComboboxField id label options value onChange
error placeholder searchPlaceholder emptyText />` wraps the label + combobox + wired error.
Used by the products form's category/supplier pickers; ready for Orders/BOM pickers.

### 4. ✅ `RowActions` (Edit/Delete dropdown column) → component  · **DONE**
`resources/js/components/row-actions.tsx` — `<RowActions label onEdit onDelete />`. Replaced the
identical dropdown block in all 5 catalog pages' `actions` column (net −96 lines with F3).

### 5. ✅ Shared Inertia prop types → `@/types/page`  · **DONE**
Every list page re-declared the same three prop shapes inline. Extracted to
`resources/js/types/page.ts` (re-exported via `@/types`): `ResourceFilters`
(`{ search; per_page }`), `TenantBrand` (`{ slug; name }`), `FlashSuccess`
(`{ success }`), and a `TenantPageProps` composite of all three. Tenant catalog pages now
write `type PageProps = TenantPageProps & { products: Paginator<Product> }`; admin
tenants/login reuse the granular types. Removed 15+ duplicated field declarations across
9 pages and killed a duplicate local `TenantBrand` in `tenant/login`.

### 6. ✅ Typed page-props accessor → `usePageProps<T>()`  · **DONE**
`resources/js/hooks/use-page-props.ts` — `usePageProps<T>()` wraps the one unavoidable
`usePage().props as unknown as T` cast so pages stop repeating it. Replaced the double-cast
(and a stray `const page = usePage()`) in 10 pages. (`welcome.tsx` keeps its natively-typed
`usePage().props` — no cast there, nothing to abstract.)

### 7. ✅ Empty-state card → `<EmptyState>` component  · **DONE**
All 7 list pages hand-built the same `<Card><CardContent className="… py-16 text-center">` empty
state (icon badge + title + description + optional action). `resources/js/components/empty-state.tsx`
— `<EmptyState icon={Icon} title description action? />` (icon is a component ref, rendered
internally as `<Icon className="size-6" />`). Applied to all 5 catalog pages + `admin/tenants`
index & trashed (trashed omits `action`). Removed ~14 lines of boilerplate per page.

### 8. ✅ Stock quantity formatting → `formatQuantity()`  · **DONE**
`resources/js/lib/format.ts` — `formatQuantity(value: number | string)` →
`Number(value).toLocaleString(undefined, { maximumFractionDigits: 4 })`. Replaced the inline
`min_stock` formatting in the raw-materials and products tables (unifies the two slightly different
call sites into one home; ready for future inventory/quantity columns).

### 9. ✅ Hardcoded frontend paths → Wayfinder route helpers  · **DONE**
The business pages hard-coded URLs (`` `/${tenant.slug}/raw-materials` ``, `"/admin/tenants"`, form
actions, links). `laravel/wayfinder` already generates typed helpers into the git-ignored
`@/routes` + `@/actions` (regenerated by the vite plugin — **do not** run
`php artisan wayfinder:generate` manually, it drops `formVariants`; use `bun run build`). Now every
page uses them, e.g. `const base = rawMaterialsRoutes.index.url({ tenant: tenant.slug })`,
`dashboard.url({ tenant })`, `destroy.url({ tenant: slug })`, `loginStore.url()`. Backend already
used named routes. **Note the collision:** a resource route module's default export is named after
the resource (e.g. `rawMaterials`), which clashes with the page's paginator prop — import it aliased
(`import rawMaterialsRoutes from '@/routes/tenant/raw-materials'`).

### 10. ✅ Repeated resource labels → `@/config/resources` descriptor  · **DONE**
`resources/js/config/resources.ts` — one `ResourceMeta` (`{ singular, plural, icon }`) per catalog
resource. Each page derives its title / breadcrumb / `<Head>` (= `plural`), toolbar + `entityLabel`
(= `singular`, `New ${singular}`), and empty-state icon/title from the descriptor instead of
repeating the wording ~9× per page. Bespoke single-use copy (page `<p>`, search placeholder,
empty-state description prose, field placeholders) stays inline.

### 11. ℹ️ Already extracted (good) — keep reusing
- `DataTable` (`resources/js/components/data-table.tsx`) — server-side list, used by 7 pages.
- `Combobox` (`resources/js/components/combobox.tsx`) — searchable FK picker.
- `ComboboxField` / `RowActions` — see items 3–4.
- `useFlashToast` — the global toast hook (see item 1).

> **Tooling note:** this repo's Biome config marks `lint/correctness/noUnusedImports` as an
> _unsafe_ fix, so `bun run check` only warns about dead imports. After removing usages in a
> refactor, run `bunx biome check --write --unsafe <files>` (scoped to the touched files) to
> drop them.

---

## Status

**Done:** `ResolvesPerPage` (B1) · `Searchable` scope (B2) · `InteractsWithTenantAssets` (B3) ·
`TenantFormRequest` (B4) · `NormalizesNumericInput` (B5) ·
`useResourceDialog` + `ResourceFormDialog` (F2) · `useDelete` + `ConfirmDeleteDialog` (F2b) ·
`ComboboxField` (F3) · `RowActions` (F4) · shared prop types `@/types/page` (F5) ·
`usePageProps` (F6) · `EmptyState` (F7) · `formatQuantity` (F8) · `RespondsWithToast` + global
`useFlashToast` (F1) · Wayfinder route helpers (F9) · `@/config/resources` descriptor (F10).
Also: `spatie/laravel-data` DTOs on **all 5 catalog resources** — Product (pilot) +
RawMaterial / Category / Supplier / Customer. Each controller's `->through()` returns an
`App\Data\XxxData` (snake_case, `#[TypeScript]`), and the page consumes the generated
`App.Data.XxxData` type instead of a hand-written interface. Regenerate with `bun run
types:generate`; the committed `resources/js/types/generated.d.ts` is reproducible.

**Remaining:** _the reuse backlog (B1–B5, F1–F8) is cleared._ New duplication should be logged
here as it appears.
