# Phase 4a — Purchase Orders Implementation Plan

> First slice of Phase 4 (Orders). Consumes the Phase-3 ledger: **Receive** posts
> `purchase_receipt` IN via `StockService`. TDD the receive → ledger integration.
> Decisions (user-confirmed / noted): receive location chosen at receive-time; currency is one
> PO-level field (default `USD`), not per-line; dialog-based UI with a line-items repeater; edit
> only while PENDING; no print page (Phase 6).

**Goal:** create a purchase order (supplier + raw-material line items), then **Receive** it into a
chosen location — atomically posting a `purchase_receipt` IN for each line and marking it RECEIVED.

## Schema (tenant migrations)

**`purchase_orders`**
- `id`, `supplier_id` (FK → suppliers, nullOnDelete), `status` string(20) default `pending`,
  `currency` string(3) default `USD`, `notes` text nullable, `user_id` (FK → users, nullable,
  nullOnDelete), `received_at` timestamp nullable, `received_location_id` (FK → locations, nullable,
  nullOnDelete), `timestamps`, `softDeletes`.

**`purchase_order_items`**
- `id`, `purchase_order_id` (FK, cascadeOnDelete), `raw_material_id` (FK → raw_materials,
  nullOnDelete), `raw_material_snapshot` json (`{name, sku, unit}` captured at write), `quantity`
  DECIMAL(15,4), `unit_cost` DECIMAL(15,4), `timestamps`.

## Enum — `app/Enums/PurchaseOrderStatus.php`
Backed string: `Pending='pending'`, `Received='received'`, `Cancelled='cancelled'`; `label()` and a
`badgeVariant()` ("secondary"/"default"/"outline") helper for the UI chip.

## Models
- `PurchaseOrder` — Fillable(supplier_id, status, currency, notes, user_id, received_at,
  received_location_id); casts status enum + received_at datetime; `belongsTo(Supplier)`,
  `hasMany(PurchaseOrderItem, 'purchase_order_id')` as `items`, `belongsTo(User)`,
  `belongsTo(Location, 'received_location_id')` as `receivedLocation`; SoftDeletes.
- `PurchaseOrderItem` — Fillable(purchase_order_id, raw_material_id, raw_material_snapshot, quantity,
  unit_cost); casts snapshot `array`, quantity+unit_cost `decimal:4`; `belongsTo(PurchaseOrder)`,
  `belongsTo(RawMaterial)`.

## `app/Actions/ReceivePurchaseOrder.php` (the crux — TDD)
```
handle(PurchaseOrder $po, Location $location, ?User $user): PurchaseOrder
  abort_unless($po->status === Pending, 422)          // only pending can be received
  DB::transaction:
    foreach ($po->items as $item):
      abort if $item->raw_material_id is null (force-deleted item)  // can't post to ledger
      $stockService->record($location, $item->rawMaterial, +$item->quantity,
                            StockMovementReason::PurchaseReceipt, $user, "PO #{$po->id}")
    $po->update(status: Received, received_at: now, received_location_id: $location->id)
  return $po
```
`StockService::record` is per-item transactional; wrapping the loop in an outer transaction makes
the whole receive atomic. (A receipt only adds stock, so it can't fail the negative guard.)

## Request — `PurchaseOrderRequest extends TenantFormRequest`
- `supplier_id` required + exists (whereNull deleted_at).
- `currency` required string size:3.
- `notes` nullable string max 1000.
- `items` required array min:1.
- `items.*.raw_material_id` required + exists raw_materials (whereNull deleted_at).
- `items.*.quantity` required numeric gt:0.
- `items.*.unit_cost` required numeric min:0.

## Controller — `PurchaseOrderController`
- `index`: `PurchaseOrder::with(['supplier','items'])->latest()->paginate()->through(PurchaseOrderData::from)`;
  pass `suppliers` (OptionData), `rawMaterials` (OptionData) for the form pickers.
- `store(PurchaseOrderRequest)`: create PO (user = request user, status pending) + items, building
  each `raw_material_snapshot` from the RawMaterial (name/sku/unit) at write; `$this->toast('Purchase order created.')`.
- `update(PurchaseOrderRequest, PurchaseOrder)`: `abort_unless(status===Pending, 422)`; update header
  + replace items (delete + recreate, re-snapshot).
- `destroy(PurchaseOrder)`: soft-delete.
- `receive(ReceiveRequest, PurchaseOrder, ReceivePurchaseOrder)`: validate `location_id` exists;
  run the action; `$this->toast('Purchase order received.')`. Catches `InsufficientStockException`
  (won't happen for receipts, but keep symmetric) → not needed.
- `cancel(PurchaseOrder)`: `abort_unless(status===Pending, 422)`; set status Cancelled;
  `$this->toast('Purchase order cancelled.')`.

## DTOs (`#[TypeScript]`)
- `PurchaseOrderData` — id, supplier (?string name), status (string value), status_label, currency,
  item_count (int), total (float = Σ qty·unit_cost), received_at (?string), created_at,
  `items: PurchaseOrderItemData[]` (for the edit form).
- `PurchaseOrderItemData` — id, raw_material_id (?int), name (from snapshot), quantity (float),
  unit_cost (float).

## Routes — `routes/tenant.php`
`Route::resource('purchase-orders', PurchaseOrderController::class)->only([index,store,update,destroy]);`
`Route::post('purchase-orders/{purchaseOrder}/receive', [..,'receive'])->name('purchase-orders.receive');`
`Route::post('purchase-orders/{purchaseOrder}/cancel', [..,'cancel'])->name('purchase-orders.cancel');`

## Tests — `tests/Feature/Tenant/PurchaseOrderTest.php`
- create a PO with 2 line items → PO + 2 items with snapshots (assertToast).
- receive posts a `purchase_receipt` IN per item at the chosen location, on-hand rises, status →
  received (+ received_at, received_location_id).
- receiving a non-pending PO → 422.
- cancel a pending PO → cancelled; cancel a received PO → 422.
- update replaces items while pending; update a received PO → 422.
- validation: no items → invalid `items`; bad quantity/unit_cost.
- guest redirect.

## Frontend — `resources/js/pages/tenant/purchase-orders/index.tsx`
- `DataTable`: columns PO # (id), supplier, status (badge via `Badge`), items (count), total
  (currency-formatted), created. Row actions (`RowActions`-style dropdown, but custom): **Edit**
  (pending), **Receive** (pending), **Cancel** (pending); always allow view/none for received/cancelled.
- Create/Edit dialog (wider `contentClassName`): supplier `ComboboxField`, a `currency` Input
  (default USD), a **line-items repeater** — React state array of `{ raw_material_id, quantity,
  unit_cost }`; each row = raw-material `ComboboxField` + qty + unit-cost inputs + a remove button;
  an "Add line" button; a live total. Submit posts `supplier_id`, `currency`, `notes`, and
  `items[i][...]` (hidden inputs indexed by row) via the Inertia `<Form>`.
- Receive dialog: a location `ComboboxField` → POST to the receive route.
- Cancel: `ConfirmDeleteDialog`-style confirm (or a plain confirm) → POST cancel.
- `purchaseOrderMeta` descriptor (icon `ClipboardList`/`ShoppingCart`) + sidebar nav (Wayfinder).

## Verification
`php artisan test --filter=PurchaseOrder`; tenants:migrate; types:generate; build; types/biome/pint;
browser smoke: create a PO with a raw-material line → Receive into a location → on-hand IN appears
in the stock-movements ledger; then purge.
