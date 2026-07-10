# Phase 3c — Stock Transfers Implementation Plan

> Third/final slice of Phase 3. Mirrors 3b (reuses `StockService`, the merged item picker, the
> ledger-table + create-only dialog pattern). TDD.

**Goal:** move a quantity of one stockable from a source location to a destination location,
**atomically** (a single transaction: OUT the source + IN the destination + record the transfer),
rejecting the whole thing if the source is short.

## Schema — `stock_transfers` (tenant migration)
- `id`, `from_location_id` (FK → locations, restrictOnDelete), `to_location_id` (FK → locations,
  restrictOnDelete), `morphs('stockable')`, `quantity` DECIMAL(15,4) (positive magnitude),
  `user_id` (FK → users, nullable, nullOnDelete), `notes` text nullable, `timestamps`.

## Model — `StockTransfer`
Fillable from_location_id/to_location_id/stockable_type/stockable_id/quantity/user_id/notes; cast
quantity `decimal:4`; `belongsTo(Location,'from_location_id')` as `fromLocation`,
`belongsTo(Location,'to_location_id')` as `toLocation`, `morphTo('stockable')`, `belongsTo(User)`.

## `StockService::transfer(...)` (new method on the existing service)
```
transfer(Location $from, Location $to, Model $stockable, float $quantity,
         ?User $user = null, ?string $notes = null): StockTransfer
  DB::transaction:                       // nested — record() opens savepoints
    $this->record($from, $stockable, -$quantity, TransferOut, $user, $notes);  // rejects if short
    $this->record($to,   $stockable, +$quantity, TransferIn,  $user, $notes);
    return StockTransfer::create([from,to,stockable morph,quantity,user_id,notes]);
```
If the source OUT throws `InsufficientStockException`, the whole transaction rolls back → no ledger
rows, no on-hand change, no transfer row.

## Request — `StockTransferRequest extends TenantFormRequest`
- `stockable` required regex `^(product|raw_material):\d+$` + `withValidator` existence check (same as 3b).
- `from_location_id` required + `exists:locations,id` (whereNull deleted_at).
- `to_location_id` required + `exists` + `different:from_location_id`.
- `quantity` required numeric `gt:0`.
- `notes` nullable string max 1000.

## Controller — `StockTransferController` (create-only; index lists transfers)
- `index`: `StockTransfer::with(['fromLocation.warehouse','toLocation.warehouse','stockable','user'])
  ->latest()->paginate()->through(StockTransferData::from)`; pass `locations` (OptionData
  "warehouse · code") + `items` (merged product/raw-material array) — identical helpers to 3b.
- `store(StockTransferRequest, StockService)`: resolve stockable from `product:5`; resolve from/to
  Location; `$request->user()`; try `$service->transfer(...)`; catch `InsufficientStockException` →
  `ValidationException::withMessages(['quantity' => 'Not enough stock at the source location.'])`;
  `$this->toast('Transfer recorded.'); return back();`.

## `StockTransferData` (`#[TypeScript]`)
id, item (string "Widget · Product"), from (string "Main · A-01"), to (string), quantity (number),
user (?string), created_at (string).

## Routes — `routes/tenant.php`
`Route::get('stock-transfers', [StockTransferController::class,'index'])->name('stock-transfers.index');`
`Route::post('stock-transfers', [StockTransferController::class,'store'])->name('stock-transfers.store');`

## Test — `tests/Feature/Tenant/StockTransferTest.php`
Seed a warehouse + two locations + a product; bring source on-hand to N via a movement or direct
`StockService`. Cover:
- transfer moves qty: source on-hand −q, destination +q, a `stock_transfers` row + TWO ledger rows
  (transfer_out at source, transfer_in at destination).
- source-short transfer → 422 `assertInvalid('quantity')`, NOTHING changed (no transfer row, no
  ledger rows, on-hand unchanged) — proves atomic rollback.
- `to == from` → `assertInvalid('to_location_id')`.
- quantity 0 → `assertInvalid('quantity')`.
- raw material path.
- guest redirect; malformed/nonexistent stockable → `assertInvalid('stockable')`.
- index renders `tenant/stock-transfers/index` with `transfers`, `locations`, `items`.

## Frontend — `resources/js/pages/tenant/stock-transfers/index.tsx`
Clone the stock-movements page but: no type toggle; fields = item `ComboboxField`, **from**
`ComboboxField`, **to** `ComboboxField`, quantity, notes. Columns: when, item, from → to, qty, by.
`stockTransferMeta` descriptor (icon `ArrowRightLeft`/`Send`); sidebar nav (Wayfinder).

## Verification
`php artisan test --filter=StockTransfer`; tenants:migrate; types:generate; build; types/biome/pint;
browser smoke (seed source stock → transfer to another location → both locations reflect it).
