# UI / UX Guidelines

How we build screens in this app. **The bar is product-grade UI, not plain
forms.** Every screen should look considered, use the design system, work in
light + dark, and give the user real feedback. If a change adds or touches UI,
it must follow this doc.

Stack: **React 19 + Inertia v3 + Tailwind v4 + shadcn/ui + lucide-react**, toasts
via **sonner**. Formatting/lint is **Biome** (see `docs/CODING-STANDARDS.md`).

---

## 1. Principles

1. **Not just a plain form.** Use cards, sections, icons, spacing, and states —
   never a bare stack of `<input>`s. Compare the admin/tenant login and dashboard
   (reference implementations below) as the minimum quality bar.
2. **Never black-and-white only.** The app has a real brand color (indigo). Use
   it for primary actions, links, focus, and brand marks. Keep large surfaces
   neutral so the color reads as an accent, not noise.
3. **Always give feedback.** Loading, success, error, and empty are all designed
   states — not afterthoughts.
4. **Accessible + responsive by default.** Labels, focus rings, keyboard reach,
   and a layout that survives from mobile to desktop.
5. **Consistent.** Mirror the established patterns/components here instead of
   inventing new ones.
6. **Plain language.** Every word the user reads is written for the person running
   the business, not the developer — no database/dev jargon (on-hand, ledger,
   snapshot, reorder point, ISO code, morph…). Keep the domain terms your users
   actually use (SKU, warehouse, BOM). Full glossary + the "would a
   non-technical person understand this?" test: [`COPY-STYLE.md`](COPY-STYLE.md).

---

## 2. Color & theme (design tokens only)

**Use semantic tokens — never hard-code hex, `zinc-*`, `black`, or `white` for
surfaces.** Tokens live in `resources/css/app.css` and adapt to light/dark
automatically.

| Use | Token classes |
|---|---|
| Primary action / brand | `bg-primary text-primary-foreground` |
| Links / accents | `text-primary` |
| Focus ring | `ring-ring` / `focus-visible:ring-ring` |
| Brand tile in sidebar | `bg-sidebar-primary text-sidebar-primary-foreground` |
| Page / card surfaces | `bg-background`, `bg-card`, `bg-popover` |
| Muted surfaces / hover | `bg-muted`, `bg-muted/50`, `bg-secondary`, `bg-accent` |
| Secondary text | `text-muted-foreground` |
| Borders / inputs | `border-border`, `border-input` |
| Destructive | `text-destructive`, `<Button variant="destructive">` |

- **Brand is indigo** (`--primary` = indigo-600 light / indigo-500 dark, white
  foreground). It's already wired to buttons, links, focus rings, checkboxes,
  default badges, and brand tiles. Don't reintroduce grayscale primaries.
- **Keep surfaces neutral.** Backgrounds, cards, borders, and muted text stay
  grayscale. Color is an accent (primary action / brand / focus), not everything.
- **Dark-mode is mandatory.** Because everything uses tokens, both themes work by
  default. If you must add a custom color, add a `dark:` variant or use a token.
  The only intentional hard-coded surface is the login marketing aside
  (`bg-zinc-950`).

---

## 3. Use shadcn/ui — don't hand-roll

Reach for the component library in `resources/js/components/ui/` before writing
raw HTML. Available: `button, input, label, card, badge, checkbox, select,
dialog, sheet, dropdown-menu, tooltip, separator, avatar, skeleton, sonner,
sidebar, breadcrumb, alert, collapsible, navigation-menu`.

- **Buttons** — use `variant` (`default | secondary | outline | ghost |
  destructive | link`) + `size`. Icon-only buttons need an `aria-label` and
  usually a `Tooltip`.
- **Inputs** — `<Input>` + `<Label htmlFor>`. Add a leading lucide icon for
  context (`Mail`, `Lock`, `Search`), a show/hide toggle for passwords, and a
  placeholder. Long values get `truncate`.
- **Forms** — use Inertia `<Form>` with the render-prop `({ processing, errors })`.
  Prefer `disableWhileProcessing`; `resetOnSuccess` where appropriate.
- **Overlays** — a right-hand **`Sheet`** (slide-over) for multi-field create/edit
  flows; a **`Dialog`** for focused confirmations. Guard against dismissing while
  a submit is in flight.
- **Menus** — `DropdownMenu` for row actions and the user/account menu; mark
  destructive items `variant="destructive"`.
- **Tables** — reach for the **`DataTable`** wrapper
  ([`components/data-table.tsx`](../resources/js/components/data-table.tsx)):
  it composes the vendored `ui/table` primitives with search, sort, pagination and
  export, and already lives in an `overflow-x-auto` wrapper with hover rows and
  `Badge` statuses. For a one-off layout, compose the `ui/table` primitives
  (`Table`, `TableHeader`, `TableRow`, `TableCell`) yourself — never a raw
  `<table>`. Hide non-essential columns below `sm`/`md`.
- **Icons** — `lucide-react` only, `size-4`/`size-5`, colored via `currentColor`.

---

## 4. UX patterns (required, not optional)

- **Loading / processing** — disable the submit, swap in a spinner
  (`LoaderCircle` + `animate-spin`) and a "…" label. Never leave a click with no
  response.
- **Empty states** — a first-class panel: icon tile + heading + one-line
  explanation + a primary CTA. Not a bare "No items.".
- **Validation errors** — render Laravel's field errors **under each field** with
  `<InputError>`, and pair `aria-invalid` + `aria-describedby` (+ `role="alert"`)
  on the input so screen readers announce them. Global/non-field errors use an
  `Alert`.
- **Field help** — when a form input isn't self-explanatory, use `<FieldLabel
  hint="…">` (a drop-in `Label` that adds a hover/focus **ⓘ** tooltip) or the
  `hint` prop on `<ComboboxField>`. One plain-language sentence; obvious fields
  (name, email, address, notes) get none. See `reusable-patterns.md` item 11.
- **Success / failure feedback** — fire a **sonner `toast`** (top-right,
  richColors), close the modal/sheet, and reflect the change in the list.
- **Destructive actions** — confirm first (Dialog), style as `destructive`.
- **Lists** — add instant client-side **search/filter** with a live count `Badge`,
  and a distinct "no results" state separate from the empty state.
- **Copy-to-clipboard** — give an affordance with feedback (icon swaps
  `Copy → Check`, plus a toast). Use the `useClipboard` hook.
- **Timestamps** — relative time ("2h ago") with the absolute time on hover
  (`title`/Tooltip).
- **Responsive** — mobile-first; the body must **never scroll horizontally**;
  the sidebar is a drawer below `lg` (1024) and pinned at `lg`+, headers/toolbars
  stack on mobile, secondary table columns hide on small screens, and wide
  popovers (calendars) cap width + reduce content. Verify at 375 / 768 / 1024.
  Full guide + verification recipe: [`RESPONSIVE.md`](RESPONSIVE.md).
- **Keyboard & a11y** — labels bound to inputs, visible `focus-visible` rings,
  icon buttons labelled, tooltip triggers keyboard-reachable, and respect the
  user's reduced-motion setting.
- **SSR safety** — anything derived from `Date`/`window`/timezone must be computed
  **post-mount** (see the dashboards' greeting) or carry `suppressHydrationWarning`.
- **Honesty** — only show data that actually exists. No invented metrics, fake
  charts, or actions without a backing endpoint.

---

## 5. Layout & shell

- **Authenticated areas** use the shadcn **inset sidebar** shell: a sticky top bar
  (`SidebarTrigger` + breadcrumbs + theme toggle), a sidebar (brand tile, nav with
  active state, footer user/account menu), and a padded `max-w` content area with
  `gap` between sections. See `central-admin-layout.tsx` / `tenant-layout.tsx`.
- **Auth pages** use the **split-screen** pattern: a branded panel (identity +
  short value props) beside a form `Card`. See the login pages.
- Pages own their `<Head title>` and pass `breadcrumbs` to the layout.

---

## 6. Reference implementations (copy these patterns)

| Pattern | File |
|---|---|
| Split-screen auth + branded panel + form card | `resources/js/pages/admin/login.tsx`, `resources/js/pages/tenant/login.tsx` |
| Inset sidebar shell (layout + sidebar/nav/user-menu) | `resources/js/layouts/central-admin-layout.tsx`, `resources/js/layouts/tenant-layout.tsx`, `resources/js/components/{admin,tenant}/*` |
| Stat cards + searchable table + create slide-over + empty states | `resources/js/pages/admin/dashboard.tsx` |
| Theme tokens (brand + neutrals, light/dark) | `resources/css/app.css` |
| Toast config (top-right, richColors) | `resources/js/components/ui/sonner.tsx` |
| Field help tooltip (`FieldLabel` / `ComboboxField hint`) | `resources/js/components/field-label.tsx`, `resources/js/pages/tenant/products/index.tsx` |

---

## 7. Do / Don't

**Do:** use tokens; reach for shadcn components; design loading/empty/error/success
states; keep surfaces neutral with an indigo accent; label + focus-ring everything;
test light **and** dark; keep it responsive; show only real data.

**Don't:** hard-code colors or `zinc/black/white` surfaces; ship a bare
unstyled form; go black-and-white only; skip feedback states; invent metrics or
dead actions; reintroduce ESLint/Prettier or reach for npm/pnpm/yarn.

---

## 8. Before finalizing a UI change

1. `bun run check` (Biome) — **0 errors** (`bun run check:ci`).
2. `bun run types:check` passes.
3. Eyeball it in **both** light and dark mode.
4. Check it at a narrow width (no horizontal scroll) and tab through it.
