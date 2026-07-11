# Copy Style — write for the user, not the developer

Every word the user reads should make sense to **the person running the business**
(a shop owner, a warehouse clerk) — not to the engineer who built it. No database
or developer jargon. Companion to [`UI-UX-GUIDELINES.md`](UI-UX-GUIDELINES.md).

## The one test

> **Would someone who has never seen a database understand this sentence?**

If not, rewrite it. Prefer short, concrete, everyday words. One plain sentence beats
a precise-but-technical one.

## Where this applies (all user-facing strings)

Page descriptions · field hints (`FieldLabel`/`ComboboxField` `hint`) · empty-state
titles/descriptions (`EmptyState`) · dialog titles/descriptions (`ResourceFormDialog`
`description`) · toasts · button labels · input placeholders · validation messages.
These live as plain `hint="…"` / `description="…"` strings in the page files, so
edits are trivial — grep the page and reword.

## Jargon → plain (don't ship the left column)

| ❌ Technical / DB-speak | ✅ Plain |
|---|---|
| on-hand quantity / on-hand levels | the amount you have / your stock |
| reorder point | minimum (stock level) |
| ISO currency code | currency code |
| barcode (EAN, UPC, …) | the barcode number on the packaging |
| snapshot / snapshotted | saved with the order (a copy is kept) |
| ledger / append-only | history / list of movements |
| morph / polymorphic / FK / nullable | *(never surface these — they're code)* |
| materialized / denormalized | *(never surface these)* |
| soft-delete | remove / archive |
| SIGNED quantity (+in/−out) | "In adds stock, Out removes it" |

## Keep the words your users already use

Plain ≠ dumbed-down. Terms a shop/factory operator genuinely uses are fine and
often clearer than a paraphrase:

- **SKU, unit, currency, supplier, customer, warehouse, stock, purchase/sales order**
- **Bill of materials** — a recognised manufacturing term; keep it (don't "simplify"
  it to "recipe"). *(This was tried and reverted — the user prefers BOM.)*

The rule is: ban **developer/database** vocabulary; keep **domain** vocabulary the
user knows.

## Writing a good field hint

One sentence: **what the field is for**, or **what happens** because of it. Concrete
example if it helps. No hint at all for obvious fields (Name, Email, Address).

- ✅ "We flag this item as low on stock once the amount you have drops to or below this number."
- ✅ "The 3-letter currency code for this order's prices, such as USD, MYR, or EUR."
- ❌ "The min_stock threshold used by the low-stock aggregate query."

## Titles & descriptions

- **Page description** — say what the screen is *for* in the user's terms
  (e.g. "Warehouses that hold stock, grouped under a location.").
- **Empty state** — name the thing and the first useful action
  ("No warehouses yet — add a warehouse under one of your locations.").
- **Dialog description** — what this form does / what will happen on save.

## When you add or rename a feature

Grep the touched page(s) for `hint=`, `description=`, `placeholder`, empty-state and
toast strings and run them through the table above before finalizing. If in doubt,
read it aloud — if it sounds like a schema, rewrite it.
