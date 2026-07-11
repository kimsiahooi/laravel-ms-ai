# Responsive & Mobile Guidelines

How this app stays usable from phone → tablet → desktop. Read this before adding
a new page, a wide component (calendar/popover/table), or anything with a fixed
width. Companion to [`UI-UX-GUIDELINES.md`](UI-UX-GUIDELINES.md).

## The one golden rule

**The page body must never scroll horizontally.** At any width, `document`'s
`scrollWidth` must equal its `clientWidth`. Wide content (tables, diagrams, code)
scrolls inside its **own** `overflow-x-auto` container — never the page.

Always eyeball three widths before calling a UI change done: **375** (phone),
**768** (iPad portrait), **1024** (iPad landscape / small laptop).

## Breakpoints (Tailwind defaults)

| Prefix | Min width | Typical device |
|---|---|---|
| _(none)_ | 0 | phone (design mobile-first) |
| `sm:` | 640 | large phone / small tablet |
| `md:` | 768 | tablet portrait |
| `lg:` | 1024 | tablet landscape / laptop |
| `xl:` | 1280 | desktop |

**Sidebar rule:** the inset sidebar is a **pinned column at `lg`+ (≥1024)** and an
**off-canvas drawer below `lg`**. This is driven by `MOBILE_BREAKPOINT = 1024` in
[`resources/js/hooks/use-mobile.tsx`](../resources/js/hooks/use-mobile.tsx) (via
`useIsMobile()`, consumed by `ui/sidebar.tsx` + the nav-user menus). It's set to
1024 **on purpose** — at 768 a pinned 256px sidebar leaves only a ~510px content
column, which crams the DataTable header (title + search + "New …" button). Below
`lg` the drawer opens from the `SidebarTrigger` (hamburger) in the header.

> **⚠️ Keep the two sidebar breakpoints in lockstep.** The JS `MOBILE_BREAKPOINT`
> (`use-mobile.tsx`) decides drawer-vs-rail; the **CSS** breakpoint inside
> `components/ui/sidebar.tsx` (the `lg:block` / `lg:flex` gates) decides when the
> rail is *painted*. If they disagree, SSR paints the rail at a width where JS
> then rips it out → the sidebar **flashes open then closes on refresh**. Both
> must be `lg`. If you ever change one, change the other.

## Patterns that keep it clean

- **Page headers / toolbars** — stack on mobile, row on `sm`+:
  `flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between`. The
  search+action group inside also stacks (`flex flex-col gap-2 sm:flex-row`) so
  the search goes full-width on a phone instead of truncating next to the button.
  This lives once in [`components/data-table.tsx`](../resources/js/components/data-table.tsx) — every list page inherits it.
- **Tables** — hide secondary columns on small screens with
  `hidden sm:table-cell` / `md:table-cell` (keep the primary/name column + row
  actions). Never let a table widen the page.
- **Dialogs / forms** — shadcn `DialogContent` is already width-capped
  (`max-w-*` + viewport margins), so forms land at ~343px on a 375px screen. Keep
  field rows single-column on mobile; only go multi-column at `sm`+.
- **Wide popovers (calendars, mega-menus)** are the #1 mobile overflow trap. Two
  rules:
  1. **Cap the width**: `max-w-[calc(100vw-1rem)]` on the `PopoverContent` so it
     can never exceed the viewport (Radix can only *shift* an oversized panel, it
     can't shrink it).
  2. **Reduce the content** on small screens — e.g. the date-range picker shows
     `numberOfMonths={1}` below `sm` and `2` above (computed from `matchMedia`;
     safe because a popover only renders on open, client-side), and stacks its
     time selects `grid-cols-1 sm:grid-cols-2`.
- **Buttons on mobile** — a primary action can go full-width for an easy tap
  target; keep it inline (`sm:w-auto`) on larger screens.
- **SSR/timezone content** — compute `Date`/`window`-derived values post-mount
  (see the hydration note in `UI-UX-GUIDELINES.md`), unrelated to layout but the
  same "don't assume the client" discipline.

## How to verify (fast, no eyeballing 20 screenshots)

A headless overflow sweep catches the body-scroll rule across every page. Log in
once, then per page/width measure the gap:

```js
const overflow = await page.evaluate(
  () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
);
// overflow > 0  → something is too wide at this breakpoint. Fix it.
```

Run it at 375 and 768 across the route list; anything `> 0` is a bug. For popovers
/ dialogs, open them first, then measure. (Heads-up: repeated logins trip the
`throttle:6,1` limiter — `php artisan cache:clear` resets it between runs.)

## Fix log (why these exist)

- **2026-07-11** — Sidebar breakpoint 768 → **1024** so iPad-portrait (768px) gets
  full-width content instead of a cramped column (this was the reported "create
  button a bit off at tablet"). DataTable header now stacks search above the
  action on phones. Date-range picker made viewport-safe (1 month + width cap +
  stacked times) — it was rendering a 480px popover on a 375px screen.
- **2026-07-11** — Fixed a sidebar **flash on refresh at 768–1023px**: after the
  breakpoint move above, `ui/sidebar.tsx` still gated the rail's CSS at `md`
  (768) while JS gated the drawer at `lg` (1024). SSR painted the rail; hydration
  swapped it for the drawer. Aligned every sidebar `md:` → `lg:` so CSS and JS
  agree — the SSR rail is now `display:none` below `lg` from first paint.
