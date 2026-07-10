# UI / UX (shadcn, branded, UX-friendly)

Build **product-grade UI, not plain black-and-white forms.** Full policy:
`docs/UI-UX-GUIDELINES.md`. Non-negotiables when writing/touching React/Inertia UI:

- **Design tokens only** — `bg-primary`, `text-primary`, `bg-secondary`,
  `bg-muted`, `text-muted-foreground`, `border-border`, `ring-ring`, `bg-card`,
  `bg-sidebar-primary`, etc. **Never** hard-code hex / `zinc-*` / `black` / `white`
  for surfaces (the login `bg-zinc-950` aside is the one intentional exception).
- **Brand is indigo** (`--primary`, light + dark, white foreground). Use it for
  primary actions, links, focus rings, brand tiles. Keep large surfaces neutral —
  color is an accent. Everything must work in **light AND dark** (tokens handle it).
- **Use shadcn/ui** (`resources/js/components/ui/`) instead of raw HTML: Button
  (with `variant`/`size`), Input+Label (leading icon, password show/hide), Card,
  Badge, Sheet (create/edit slide-over), Dialog (confirm), DropdownMenu, Tooltip,
  Skeleton, sonner `toast`. Tables are hand-built semantic `<table>` in
  `overflow-x-auto` (no Table component).
- **Always give feedback / handle every state:**
  - processing → disable submit + `LoaderCircle` spinner + "…" (`disableWhileProcessing`)
  - validation → field errors under inputs via `<InputError>` + `aria-invalid` +
    `aria-describedby`; success/error → sonner `toast` (top-right, richColors)
  - **field help** → for inputs that aren't self-explanatory, use `<FieldLabel
    hint="…">` (drop-in `Label` with a hover/focus ⓘ tooltip) or `<ComboboxField
    hint>`; one plain sentence, obvious fields get none (see `docs/reusable-patterns.md` item 11)
  - **empty state** = designed panel (icon + heading + explanation + CTA), plus a
    separate "no results" state for filtered lists
- **UX affordances:** instant client-side search + live count, copy-to-clipboard
  with icon-swap + toast, relative time with absolute on hover, destructive actions
  confirmed and styled `destructive`.
- **Layout:** authed areas use the inset-sidebar shell
  (`layouts/central-admin-layout.tsx`, `layouts/tenant-layout.tsx`); auth pages use
  the split-screen brand-panel + form-card pattern (`pages/*/login.tsx`).
- **a11y + responsive + SSR:** bind labels, `focus-visible` rings, `aria-label` on
  icon buttons; no horizontal body scroll, hide non-essential columns on small
  screens; compute `Date`/`window`-derived content post-mount or use
  `suppressHydrationWarning`.
- **Honest data only** — no invented metrics, fake charts, or actions without an
  endpoint.

Mirror the reference implementations: `pages/admin/dashboard.tsx` (stats +
searchable table + create Sheet + empty states), `pages/{admin,tenant}/login.tsx`,
`resources/css/app.css` (tokens). Run `bun run check` (0 errors) + `types:check`,
and eyeball light **and** dark before finalizing.
