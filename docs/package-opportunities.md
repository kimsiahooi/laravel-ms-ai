# Package Opportunities — buy vs. build

A pragmatic audit of where a **stable, popular** package would let us write **less code**
than hand-rolling, and where we're already well-covered (so we *don't* add bloat).

> Principle: only add a dependency when it removes real code or real risk. Prefer packages
> the Laravel/React ecosystem has clearly standardized on. Don't add a lib for something the
> platform already does (e.g. `Intl` for dates).

## TL;DR

The stack is already well-packaged — **Tenancy** (stancl), **Fortify** (+ passkeys), **Inertia**,
**TanStack Table**, **radix/shadcn** + **cmdk** + **sonner**, **Wayfinder** (route typing), **React
Compiler**, `clsx`+`tailwind-merge`. Very little is truly "from scratch." **One** high-value
addition is worth doing now; the rest are for later phases or are deliberately skipped.

---

## ✅ Adopt now — `spatie/laravel-data` (+ `spatie/laravel-typescript-transformer`)

**The problem (real duplication):** each list controller hand-maps a model to an array —
`->through(fn (Product $p): array => ['id' => …, 'name' => …, …])` — and each page **re-declares
the same shape by hand** (`type Product = {…}`, `type Paginator<T>`, `type PageProps` — 11
hand-written types across the tenant pages). Two copies of one shape ⇒ they drift.

**The package:** define the shape **once** as a `Data` class; `laravel-data` serializes it for
Inertia, and `typescript-transformer` **auto-generates the matching TypeScript** the pages import.

**Win:**
```php
// app/Data/ProductData.php  — one definition
class ProductData extends Data {
    public function __construct(
        public int $id, public string $name, public string $sku,
        public ?string $image_url, public ?string $category, /* … */
    ) {}
}
// controller: ->through(fn (Product $p) => ProductData::from($p))   // no hand-map array
```
```ts
// resources/js/types/generated.d.ts  — generated, not hand-written
import type { ProductData } from '@/types/generated';   // pages import this
```
- Less code (drops the per-controller map arrays **and** the per-page type blocks).
- **No backend↔frontend drift** — one source of truth, type-safe end to end.
- Pays off **more the sooner** it lands: Orders / Manufacturing add many more shapes.

**Effort:** moderate — install + config + a `Data` class per resource + a `typescript:transform`
step wired into `bun run build`. **Risk:** moderate — Pest tests assert prop shapes, so the
generated payload must match today's arrays exactly (keep the 122 tests green).
**Compatibility:** confirm the current `laravel-data` release supports this app's Laravel version
before adopting.

**Verdict:** strongest single win — but it changes the data pattern for *every* resource, so do it
as a **focused cycle** (pilot on Products, verify tests, then roll out), not a drive-by install.

---

## 🧩 Already covered — do NOT add these

| Need | Already using | Note |
| --- | --- | --- |
| Route URLs typed in TS | `laravel/wayfinder` (installed) | starter pages use it; tenant pages hand-build `/${slug}/…` because of the `{tenant}` path param — fine as-is |
| Tailwind class merging | `clsx` + `tailwind-merge` (`cn`) | the standard combo |
| Dates / "time ago" | native `Intl.RelativeTimeFormat` / `DateTimeFormat` in `lib/format.ts` | zero-dep, correct — **no `date-fns`/`dayjs` needed** |
| Tables (server-side) | `@tanstack/react-table` | our `DataTable` is the thin shadcn wrapper, not a reinvention |
| Combobox / command / toasts / icons | `cmdk` + `radix` + `sonner` + `lucide` | shadcn primitives are copy-paste by design |
| Auth / passkeys / multi-tenancy | Fortify / `@laravel/passkeys` / stancl | all packaged |

---

## 🔜 Reach for these when the phase arrives (don't install early)

| Phase / need | Package | Why not hand-roll |
| --- | --- | --- |
| **Money & totals** (Phase 4 orders) | `brick/money` (or `akaunting/laravel-money`) | currency-safe integer math + rounding; never store money as float |
| **PDF invoices** (Phase 6 PO/SO) | `spatie/laravel-pdf` (Browsershot) or `barryvdh/laravel-dompdf` | spec already plans print-styled Inertia pages now, PDF later |
| **CSV/Excel export** (if needed) | `spatie/simple-excel` (light) or `maatwebsite/excel` | streaming export/import without buffering |
| **Granular roles/permissions** | `spatie/laravel-permission` | only if central/tenant roles get finer than the current guard split |
| **Status enums with labels** | native PHP `enum` first | add `archtechx/enums` only if you want label/collection helpers |

---

## 🤔 Deliberately skipped (for now)

- **`react-hook-form` + `zod`** — Inertia's `<Form>` + server-side validation is the source of
  truth and is currently *simpler*; adding RHF+zod would duplicate the validation rules on the
  client. Revisit only if a form gets genuinely complex (multi-step, dynamic arrays).
- **`spatie/laravel-medialibrary`** — overkill for one product image, and it wouldn't help the
  nginx serving constraint we already solved (it generates file URLs too). Reconsider only if we
  need conversions/thumbnails/multiple images per model.
- **`spatie/laravel-activitylog`** — nice audit trail per tenant, but no requirement yet.
