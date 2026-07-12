# Near-term Addons — Traceability, Stock Visibility, BOM, UI/UX

> **Status (2026-07-12):** Item 0 (recipe → BOM rename) is **done** — commit `6cd4d01`.
> Items 1–11 below are **pending**, to be built in the suggested order. The longer-term
> MY/SG market roadmap is in the appendix (on hold).

## Context

Before the big MY/SG market roadmap (condensed at the bottom, on hold), the user wants six concrete
improvements to the **current** app so day-to-day work is clearer and every record is accountable:
full traceability, on-hand visibility exactly where stock decisions are made, the BOM visible on the
products page, and a shadcn UI + mobile/tablet responsive polish.

**Decisions locked with the user:**
- **Traceability → full activity log** (`spatie/laravel-activitylog`: who + what-changed + when, viewable).
- **BOM display → inline expandable row** on the products list (no separate detail page).
- **Rename recipe → BOM now** (also completes roadmap "Phase 0"), so code + new UI are consistently "BOM".
- Standing bar (from the roadmap): **humanize all copy** (`docs/COPY-STYLE.md`); keep domain terms (BOM, SKU).

---

## Work items

### 0. Rename recipe → BOM ✅ done (commit `6cd4d01`)
Straight reverse of the earlier rename — the codebase is uniformly "recipe" today:
- Code: `RecipeItem→BomItem` (table `recipe_items→bom_items`), `Product::recipeItems()→bomItems()`,
  `RecipeItemData→BomItemData`, `RecipeRequest→BomRequest`, `ProductController::updateRecipe→updateBom`,
  route `products.recipe→products.bom` (path `/recipe→/bom`), `ProductData` field `recipe→bom`, prop
  `productRecipes→productBoms`, frontend `recipe*→bom*`; UI display term **"BOM"**.
- `git mv` the 5 renamed files, `composer dump-autoload`, regenerate Wayfinder (`--with-form`) + TS types.
- Docs: flip recipe→BOM in `COPY-STYLE.md`, `UI-UX-GUIDELINES.md`, `USER-GUIDE.md`, `reusable-patterns.md`
  **but keep the humanization conventions** (BOM is a kept domain term). Update the two agent memories.
- Demo tenant DB: rename table `recipe_items → bom_items` (data preserved).
- Files: `app/Models/RecipeItem.php`, `app/Data/RecipeItemData.php`, `app/Http/Requests/Tenant/RecipeRequest.php`,
  `app/Models/Product.php`, `app/Data/ProductData.php`, `app/Http/Controllers/Tenant/{Product,ProductionOrder}Controller.php`,
  `routes/tenant.php`, 4 tenant migrations, `tests/Feature/Tenant/{RecipeTest,ProductionOrderTest}.php`,
  `resources/js/pages/tenant/{products,production-orders}/index.tsx`, `resources/js/components/combobox-field.tsx`.

### 1. Full activity log (traceability) — spatie/laravel-activitylog
Today only stock movements/transfers + orders carry who+when (`user_id`); catalog CRUD has no actor/history.
**spatie/laravel-activitylog** captures exactly what's wanted: on every create/edit/delete it records the
**causer (who)**, the **event** (created / updated / deleted), and for edits both the **old and new values**
of the changed fields (activity `properties`: `old` + `attributes`), plus **when**.
- Add the package; publish a **tenant** migration for `activity_log` (per-tenant DB — the default connection
  is the tenant connection under tenancy, so it lands in each tenant DB).
- Add the `LogsActivity` trait to the tenant models (`Product, RawMaterial, Category, Supplier, Customer,
  Location, Warehouse, PurchaseOrder, SalesOrder, ProductionOrder, WarehouseReorderLevel, BomItem`),
  configured with `logOnlyDirty()` + logged attributes so updates capture **old → new**; causer resolves
  from the authed tenant user.
- **Viewable history:** a new read-only **Activity** page listing {when (relative + absolute on hover), who,
  action, record, and the field changes rendered as **old → new**} — like the stock-movements index;
  search/filter by entity + user. New route + `ActivityController` + Data +
  `resources/js/pages/tenant/activity/index.tsx` + a sidebar nav item. (A per-record history view can come later.)
- The existing append-only stock ledger + order snapshots stay as-is (source of truth for stock/orders);
  the activity log adds the catalog/who + old→new layer and one unified trail. Reuse `DataTable`.
- Verify: create/edit/delete a product → row appears in Activity with correct user and changed fields.

**Stock status colors (shared — warehouse list, detail page, and dialogs).** `OK` = on hand above the
reorder level (neutral); **`Low` = warning / amber** (0 < on hand ≤ reorder level); **`Out of stock` =
critical / red** (on hand = 0) using the `destructive` token (with dark-mode variants). "Critical" = out of
stock for now; a separate critical threshold can be added later if wanted.

### 2. Show current stock on the warehouse list
`warehouses/index.tsx` shows only name/location/code/address; on-hand lives only on the detail page.
- Extend `WarehouseController@index` to aggregate per warehouse from `warehouse_stocks` + `warehouse_reorder_levels`:
  **items_in_stock** (qty > 0), **low_stock** (0 < qty ≤ reorder level), **out_of_stock** (qty = 0). Add these to
  `WarehouseData` (or a summary map prop). Reuses the counts the detail page already computes.
- Add an **"Items in stock"** column + status `Badge`s: amber **"N low"** when `low_stock > 0` and red
  **"N out of stock"** when `out_of_stock > 0` (both deep-link into the detail page's reorder view). Keep responsive.
- Files: `app/Http/Controllers/Tenant/WarehouseController.php`, `app/Data/WarehouseData.php`, `warehouses/index.tsx`.

### 3. Inline-expandable BOM on the products list
BOM is dialog-only today; `product.bom` (post-rename) is already loaded in the list payload.
- Add lightweight **expandable-row** support to `DataTable` (optional `renderExpanded?: (row) => ReactNode`
  + a leading chevron toggle + an expanded `<TableRow>`), or implement expansion locally in the products page.
- Expanded panel shows the BOM (material · qty/unit) read from `product.bom`; a product with no BOM shows a
  subtle "No BOM yet" + a "Set BOM" affordance. The existing **BOM edit dialog** stays reachable from the row menu.
- Files: `resources/js/components/data-table.tsx` (optional expand support), `resources/js/pages/tenant/products/index.tsx`.

### 4. Show current on-hand in the stock movement + transfer dialogs
Neither dialog shows on-hand today (the transfer dialog even *says* "can't exceed on hand" with no number),
and `StockService` exposes only private, lock-acquiring readers.
- Add a public **non-locking** read: `StockService::onHand(Warehouse $w, Model $stockable): float` (queries
  `warehouse_stocks` by `(warehouse_id, stockable_type=getMorphClass(), stockable_id)`).
- Add a small endpoint `GET /{tenant}/stock/on-hand?warehouse={id}&stockable={product:id}` → `{ on_hand, unit,
  reorder_level }`, resolving the encoded picker value the same way `store()` does (split on `:`, `BuildsStockPickers` aliases).
- In both create dialogs, when a warehouse (movement) / **from-warehouse** (transfer) + item are selected,
  fetch and show **"On hand: 42 pcs"** beside the quantity field, **colored by the shared status** (amber when
  low, red when 0); transfer also uses it as the real max hint.
- Files: `app/Services/StockService.php`, new action/route (extend `StockMovementController` or a small
  `StockLookupController`), `stock-movements/index.tsx`, `stock-transfers/index.tsx`.
- Verify: selecting item + warehouse shows correct on-hand; transfer shows source-warehouse on-hand.

### 5 + 6. shadcn UI/UX polish + mobile/tablet responsive (cross-cutting — applied to all the above)
- Every new/edited screen follows `docs/UI-UX-GUIDELINES.md` + `docs/COPY-STYLE.md`: shadcn components +
  design tokens, loading/empty/error/success states, plain-language copy, light **and** dark. "Current UI can
  be adjusted" — free to refine touched screens (e.g. align multi-field create/edit to the guideline's `Sheet`).
- **Fix the responsive table gap:** wrap the `DataTable` `<Table>` in an `overflow-x-auto` container (the
  guideline requires it; the component currently relies on shell `overflow-x-hidden` + column hiding, so a
  375px table that can't shed enough columns clips instead of scrolling).
- Ensure the new UI (activity page, warehouse badges, product expand panel, dialog on-hand) stacks and stays
  readable at **375 / 768 / 1024**; no horizontal body scroll (`docs/RESPONSIVE.md`).

### 7. Reports page (date-filterable: day / month / year)
A new **Reports** section (sidebar) with a date-range filter offering **day / month / year** presets + a custom
range — reuse the existing `resources/js/components/date-range-picker.tsx`. Starter reports, all period-scoped
and exportable (item 10):
- **Stock on-hand** (current snapshot by warehouse/item, with the low/out status colors),
- **Stock movements summary** (in / out / adjustment / transfer over the period, by reason / item / warehouse),
- **Sales summary** (fulfilled orders: qty + line totals from `unit_price`),
- **Purchase summary** (received orders: qty + totals from `unit_cost`),
- **Production summary** (produced qty + materials consumed),
- **Low-stock / out-of-stock** (items at/below reorder level).
Full **valuation / COGS** reports depend on costing (roadmap Phase B); until then these are quantity +
order-line-amount based. Files: `Tenant/ReportController` + Data DTOs, `resources/js/pages/tenant/reports/*`,
sidebar nav. Reuse `DataTable` + `date-range-picker.tsx`.

### 8. Stock take (physical count → adjustments) — separate module
Different from Reports. Flow: start a stock take for a warehouse → system lists items with current on-hand →
enter the **counted** qty per item → show **variance** (counted − system) → confirm posts adjustments via
`StockService::setLevel()` (new reason `stock_take`) atomically, and saves the count for history.
- New tenant tables `stock_takes` (warehouse, user, status, counted_at, notes) + `stock_take_items`
  (stockable, system_qty snapshot, counted_qty, variance) → a traceable stock-take trail.
- Extend `StockMovementReason` with `StockTake`. Files: migrations + models + `Tenant/StockTakeController` +
  Data + `resources/js/pages/tenant/stock-takes/*` + sidebar. Reuse `StockService`, `BuildsStockPickers`.

### 9. Purchase Return / Sales Return
Not in the current system (orders go pending → received/fulfilled/cancelled; no returns). Add two modules:
- **Purchase Return** — return received raw materials to a supplier → stock **OUT** (reason `purchase_return`).
- **Sales Return** — customer returns products → stock **IN** (reason `sales_return`).
Each: list + create, optionally **linked to the source PO/SO** (prefill from a received/fulfilled order) or
standalone; snapshots + `user_id` for traceability; posts reverse stock movements via `StockService`.
- Extend `StockMovementReason` with `PurchaseReturn` + `SalesReturn`. Files: migrations
  (`purchase_returns`/`sales_returns` + items) + models + controllers + Data + pages + routes + sidebar + enum.
  Mirror the existing PO/SO structure and the receive/fulfill actions.

### 10. Export CSV + Excel — every table/page (cross-cutting)
- Add **`maatwebsite/excel`**. Give every index/table an **Export** control (CSV + Excel) that exports the
  **current filtered dataset** (respects search/filters) server-side.
- Reusable pattern: a per-resource export (columns matching the table) + a shared frontend `ExportMenu` added
  to the `DataTable` toolbar, wired on every list (catalog, inventory, orders, movements/transfers, reports,
  activity, stock takes, returns). Files: composer (`maatwebsite/excel`), `app/Exports/*`, export routes per
  resource (or one param'd endpoint), `resources/js/components/export-menu.tsx`, `data-table.tsx` toolbar slot.

### 11. Unsaved-changes guard on dialogs (cross-cutting UX)
When a CRUD form is **dirty** (values changed) but not yet submitted, closing the dialog (X button,
click-outside, or ESC) prompts a confirm — **"Discard changes?"** → *Discard* (close) or *Keep editing* (stay)
— instead of silently losing input. Applies to every CRUD dialog.
- Dirty detection: use Inertia `useForm().isDirty` where the shared `ResourceFormDialog` (Inertia `<Form>`) is
  used; for the custom local-state dialogs (products BOM editor, stock movement, stock transfer, + the new
  stock take / returns dialogs) compare current state to an initial snapshot.
- Intercept the Radix `Dialog` close paths (`onOpenChange`, `onInteractOutside`, `onEscapeKeyDown`); show a
  small confirm (reuse the existing `ConfirmDeleteDialog` Dialog pattern, or add shadcn `alert-dialog`).
  Centralize in `ResourceFormDialog` + a reusable `useUnsavedChangesGuard` hook / `ConfirmDiscardDialog`.
- Files: `resources/js/components/resource-form-dialog.tsx`, `resources/js/hooks/use-resource-dialog.tsx`, a new
  guard hook/component, and the custom dialogs listed above.

---

## Suggested order
`0 rename ✅ → 4 on-hand → 2 warehouse list → 3 BOM inline → 1 activity log → 8 stock take → 9 returns → 7 reports → 10 exports`; **5 / 6 / 11 (UI, responsive, unsaved-changes guard) woven throughout with a final pass**.

Each item ships TDD (Pest), driven end-to-end in the running app, gates green.

## Verification
- **Tests (Pest):** activity logs causer + old→new on CRUD; warehouse-index aggregates (in-stock / low / out)
  correct; on-hand endpoint returns the right qty; BOM rides in the product payload; a stock take posts the
  right variance adjustments; purchase/sales returns move stock the correct direction; reports total correctly
  for a date range; every export returns the filtered rows.
- **Run the app** (Herd) and drive each: CRUD → Activity page (old→new); warehouse list badges (amber/red);
  expand a product's BOM; movement/transfer dialog shows colored on-hand; run a stock take; a purchase + a
  sales return; a report filtered by day/month/year; export CSV + Excel from a few tables; edit a form then try
  to close the dialog → the discard / keep-editing prompt appears. Check **light + dark** at **375 / 768 / 1024**.
- **Gates:** `vendor/bin/pint --dirty`, `bun run check` (0 errors), `bun run types:check`, `php artisan test`.
- **Local DBs:** after step 0's rename + the new migrations (`activity_log`, `stock_takes`/`stock_take_items`,
  `purchase_returns`/`sales_returns` + items), run `php artisan migrate` + `php artisan tenants:migrate` on
  local dev DBs (tests don't migrate them); demo `bom_items` handled in step 0.

---

## Assumptions to confirm (new items)
- **Reports:** the starter set above, quantity + order-amount based; full valuation/COGS waits on costing (roadmap B).
- **Stock take:** a full module with saved `stock_takes` records (traceable) that posts adjustments — not an ad-hoc adjust.
- **Returns:** dedicated Purchase/Sales Return modules posting reverse stock movements, optionally linked to a source order.
- **Export:** `maatwebsite/excel`, CSV + Excel, exporting the current filtered rows on every list.

## Appendix — longer-term MY/SG roadmap (on hold, resumes after these addons)
Positioning: MY/SG manufacturing SME inventory that **owns** stock/BOM/production/cost and **integrates**
with accounting (SQL/AutoCount/Xero) for tax + e-invoicing. Decisions: integrate-not-build compliance;
general 80% manufacturing; costing+COGS yes; multi-user roles; BOM terminology; QR codes; humanize copy.
- **A) Localization:** MYR/SGD base, company tax profile (SSM/TIN/SST · UEN/GST), lightweight tax capture on
  documents, document numbers, printable Invoice/DO/PO, accounting CSV/Excel export.
- **B) Costing + UOM:** multi-UOM conversions; weighted-average cost + inventory valuation + COGS; rebuilt dashboard.
- **C) Order realism:** partial receipt/fulfillment/backorders; reorder → PO suggestions.
- **D) Platform:** multi-user + Owner/Manager/Store roles; wire up password reset + 2FA (Fortify routes are stripped).
- **E) Traceability + QR + batch:** (activity log now done in item 1); QR-code labels + phone-camera scan; optional batch/lot + expiry.
- **Don't build:** native e-invoicing/tax filing, full MRP shop-floor, multi-level BOM, serial numbers, bin sub-locations.
