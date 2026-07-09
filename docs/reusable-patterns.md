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

### 2. 🔜 Searchable-index query → a scope/helper  · **Proposed (medium)**
**What:** the 5 catalog `index()` methods share the same shape — a grouped `OR LIKE`
search over N columns, then `->latest()->paginate($perPage)->withQueryString()->through(...)`.
Only the **columns** and the **`through` map** differ.
**Proposal:** a query helper/scope, e.g. `scopeSearch(Builder $q, ?string $term, array $columns)`
(a shared trait/macro), used as `Model::query()->search($search, ['name','sku','barcode'])`.
Keep the `->through()` map in each controller (it's genuinely per-resource).
**Impact:** removes the repeated closure in 5 controllers. **Risk:** low-medium (behavior
identical, backed by existing search tests). **Files:** the 5 `Tenant/*Controller.php`.

### 3. 🔜 `FormRequest::authorize()` boilerplate → base/trait  · **Proposed (low)**
**What:** every tenant `FormRequest` repeats `authorize(): bool { return $this->user() !== null; }`.
**Proposal:** a `TenantFormRequest` base class (or an `AuthorizesTenantUser` trait) providing the
default `authorize()`. **Impact:** small but removes copy-paste from every request.
**Risk:** low.

### 4. 🔜 `min_stock` blank→0 coercion → shared `prepareForValidation`  · **Proposed (low)**
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

### 2. 🔜 Resource dialog state → `useResourceDialog` hook  · **Proposed (medium)**
**What:** 6 index pages repeat the same create/edit dialog state machine —
`formOpen`, `editing`, per-field `useState`, and `openCreate` / `openEdit(row)` / `resetForm`.
**Proposal:** a `useResourceDialog<T>()` hook owning `open`, `editing`, `openCreate`,
`openEdit`, `close`, so pages keep only their field state. (Products' image-preview logic —
`previewRef` + `setPreview` + revoke — is a good candidate to fold in as `useImageUpload()`.)
**Impact:** trims boilerplate on every catalog page. **Risk:** medium (per-page field differences;
verify each dialog still opens/edits/resets). **Files:** the 6 `*/index.tsx` with dialogs.

### 3. 🔜 `ComboboxField` (Label + Combobox + InputError) → component  · **Proposed (low)**
**What:** `products/index.tsx` repeats the `<Label>` + `<Combobox>` + `<InputError>` block
per FK picker (category, supplier). Orders/BOM/production will add more FK pickers.
**Proposal:** `resources/js/components/combobox-field.tsx` — `<ComboboxField label options value
onChange error name />` (renders the label, the combobox, the hidden input, and the error).
**Impact:** small now (2 uses), compounding as more entity pickers arrive. **Risk:** low
(self-contained, types + build verify).

### 4. ℹ️ Already extracted (good) — keep reusing
- `DataTable` (`resources/js/components/data-table.tsx`) — server-side list, used by 7 pages.
- `Combobox` (`resources/js/components/combobox.tsx`) — searchable FK picker.
- `useFlashToast` — the global toast hook (see item 1).

---

## Suggested order

1. ✅ `ResolvesPerPage` trait — done.
2. Flash-toast consolidation (item F1) — highest impact; do as a focused pass with a browser smoke.
3. `ComboboxField` (F3) — quick, unlocks cleaner Orders/BOM forms.
4. `useResourceDialog` (F2) + backend `TenantFormRequest` base (B3) + search scope (B2).
