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

### 4. 🔜 `FormRequest::authorize()` boilerplate → base/trait  · **Proposed (low)**
**What:** every tenant `FormRequest` repeats `authorize(): bool { return $this->user() !== null; }`.
**Proposal:** a `TenantFormRequest` base class (or an `AuthorizesTenantUser` trait) providing the
default `authorize()`. **Impact:** small but removes copy-paste from every request.
**Risk:** low.

### 5. 🔜 `min_stock` blank→0 coercion → shared `prepareForValidation`  · **Proposed (low)**
**What:** `RawMaterialRequest` and `ProductRequest` share the same
`prepareForValidation()` coercing blank `min_stock` to `0`.
**Proposal:** a small `NormalizesNumericInput` trait (or a `mergeDefault()` helper). Only 2
callers today — extract when a 3rd appears, or now if a base request (item 3) exists.
**Risk:** low.

---

## Frontend (React)

### 1. 🔜 Flash toasts: two conventions → the existing `useFlashToast` hook  · **Proposed (HIGH value)**
**The problem — the codebase has drifted into two flash conventions:**

- **Convention A (imperative, per-page):** controllers `return back()->with('success', 'X')`;
  `HandleInertiaRequests` shares `flash.success`; **each of the 7 list pages hand-rolls its own
  identical `flashToast(page)`** and calls it in Inertia `onSuccess`. Used by the 5 tenant catalog
  pages + `admin/tenants/{index,trashed}`.
- **Convention B (reactive, global):** controllers `Inertia::flash('toast', ['type'=>'success',
  'message'=>'X'])`; the **`useFlashToast()` hook** (`resources/js/hooks/use-flash-toast.ts`)
  listens on `router.on('flash')` and toasts. **It is already mounted globally** inside the
  `<Toaster>` (`resources/js/components/ui/sonner.tsx`), so it fires app-wide with **zero
  per-page code**. Used today by `Settings/ProfileController`.

**Recommendation:** standardize on **Convention B** and delete Convention A. It's reactive,
global, typed (`FlashToast` in `resources/js/types/ui.ts`), and supports all toast types
(success/info/warning/error), not just success.

**Migration:**
1. Backend — replace `->with('success', 'X')` with `Inertia::flash('toast', ['type' => 'success',
   'message' => 'X'])`. Wrap it in a controller trait `RespondsWithToast` (`protected function
   toast(string $message, string $type = 'success')`) so call sites stay one line.
2. Frontend — delete the 7 local `flashToast` functions and their `onSuccess={… flashToast(x) …}`
   calls (keep the other `onSuccess` work like `setFormOpen(false)`). The global hook handles the
   toast.
3. Tests — the catalog/admin feature tests assert `->assertSessionHas('success')`; update these to
   assert the new flash payload (or drop them and keep the `assertRedirect`). ~20 assertions across
   the 5 catalog + 2 tenant-admin test files.

**Impact:** removes 7 copy-pasted helpers + all the per-page toast plumbing; one consistent flash
path. **Risk:** medium — touches ~6 controllers, ~7 pages, ~20 test assertions, and changes toast
UX, so it wants a **browser smoke** (create/update/delete → toast still appears). Best done as a
focused pass.

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

### 5. ℹ️ Already extracted (good) — keep reusing
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
`useResourceDialog` + `ResourceFormDialog` (F2) · `useDelete` + `ConfirmDeleteDialog` (F2b) ·
`ComboboxField` (F3) · `RowActions` (F4). Also: `spatie/laravel-data` DTO pilot on Products.

**Remaining:**
- Backend `TenantFormRequest` base for `authorize()` (B4) + `min_stock` coercion (B5) — small.
- Flash-toast consolidation (F1) — intentionally **skipped**.
- Roll `laravel-data` out to the other resources (pilot proven on Products).
- (Admin tenants pages keep their own archive/restore/force-delete flow — not part of the
  catalog dialog/delete abstractions.)
