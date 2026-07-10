# Phase 3b — Stock Ledger + Movements Implementation Plan

> REQUIRED SUB-SKILL: superpowers:subagent-driven-development + TDD (the StockService
> negative-stock guard, transaction, and on-hand materialization MUST have failing tests first).
> Second slice of Phase 3. Design locked with the user: merged item picker + IN/OUT/ADJUST;
> ADJUSTMENT = set on-hand to a value; ledger + entry form only (no separate stock-levels screen).

**Goal:** an append-only stock ledger + materialized on-hand, mutated only through a locked,
transactional `StockService` that rejects negative stock; plus a manual **Stock Movements** screen.

## Schema (tenant migrations)

**`stock_movements`** (append-only ledger — never updated/deleted, no soft-delete)
- `id`, `location_id` (foreignId → locations, restrictOnDelete), `morphs('stockable')`
  (→ `stockable_type` + `stockable_id`), `quantity` DECIMAL(15,4) (**signed**: + = in, − = out),
  `reason` string(30), `user_id` (foreignId → users, nullable, nullOnDelete), `notes` text nullable,
  `timestamps`.

**`location_stocks`** (materialized on-hand)
- `id`, `location_id` (FK → locations, cascadeOnDelete), `morphs('stockable')`,
  `quantity` DECIMAL(15,4) default 0, `timestamps`.
- `unique(['location_id', 'stockable_type', 'stockable_id'])`.

## Morph map (non-enforcing — passkeys must stay unaffected)
`app/Providers/AppServiceProvider::boot()`:
`Relation::morphMap(['product' => Product::class, 'raw_material' => RawMaterial::class]);`
So `stockable_type` stores `product` / `raw_material`. Use `$model->getMorphClass()` when writing.

## Enum
`app/Enums/StockMovementReason.php` — backed string enum: `PurchaseReceipt='purchase_receipt'`,
`SalesFulfillment='sales_fulfillment'`, `ProductionConsume='production_consume'`,
`ProductionOutput='production_output'`, `TransferIn='transfer_in'`, `TransferOut='transfer_out'`,
`Adjustment='adjustment'`. Add a `label(): string` (humanized) for display.

## Models
- `StockMovement` — fillable location_id/stockable_type/stockable_id/quantity/reason/user_id/notes;
  casts `reason` => enum, `quantity` => `decimal:4`; `belongsTo(Location)`, `morphTo('stockable')`,
  `belongsTo(User)`. No SoftDeletes.
- `LocationStock` — fillable location_id/stockable_type/stockable_id/quantity; cast quantity
  `decimal:4`; `belongsTo(Location)`, `morphTo('stockable')`.

## `app/Services/StockService.php` (the crux — every mutation goes through here)
```
InsufficientStockException extends \RuntimeException   // app/Exceptions/

// core, delta-based. delta may be + or -.
record(Location $location, Model $stockable, float $delta, StockMovementReason $reason,
       ?User $user = null, ?string $notes = null): StockMovement
  DB::transaction:
    $stock = LocationStock::where(location_id, stockable_type=getMorphClass, stockable_id)
             ->lockForUpdate()->first();     // row lock (or none yet)
    $current = (float) ($stock?->quantity ?? 0);
    $new = $current + $delta;
    if ($new < 0) throw new InsufficientStockException(...);   // controller → 422 on 'quantity'
    upsert LocationStock to $new;
    return StockMovement::create([... 'quantity' => $delta, 'reason' => $reason, 'user_id' => $user?->id ...]);

// set on-hand to an absolute target (ADJUSTMENT); computes delta under the same lock.
setLevel(Location, Model $stockable, float $target, ?User, ?string $notes): StockMovement
  // same transaction/lock; $delta = $target - $current; record(reason: Adjustment)
```
Manual movement mapping (in the controller): **IN** → `record(+qty, Adjustment)`; **OUT** →
`record(-qty, Adjustment)` (service rejects if it would go negative); **ADJUSTMENT** →
`setLevel(qty)`.

## Request — `StockMovementRequest extends TenantFormRequest`
- `stockable` required string matching `^(product|raw_material):\d+$` (the merged-picker value);
  validate the referenced row exists (custom rule / after-resolution check).
- `location_id` required + `exists:locations,id` (whereNull deleted_at).
- `type` required `in:in,out,adjustment`.
- `quantity` required numeric `min:0` (magnitude; for adjustment it's the target, ≥ 0).
- `notes` nullable string max 1000.

## Controller — `StockMovementController` (create-only ledger; no edit/delete)
- `index`: `StockMovement::with(['location.warehouse','stockable','user'])->latest()->paginate()
  ->through(StockMovementData::from)`; also pass `locations` (OptionData: id + "warehouse · code")
  and `items` (a plain array `{ value:'product:5', label:'Widget · Product' }` merging products +
  raw materials) for the form pickers.
- `store`: resolve stockable from the `product:5` value via the morph map; get `$request->user()`;
  map type→service call; catch `InsufficientStockException` → `throw ValidationException::withMessages(
  ['quantity' => 'Not enough stock at this location.'])`; on success `$this->toast('Movement recorded.')`
  then `back()`.

## `StockMovementData` (laravel-data, `#[TypeScript]`)
id, location (string "warehouse · code"), item (string "Widget · Product"), quantity (number, signed),
reason (string label), user (?string), created_at (string). `fromStockMovement`.

## Routes — `routes/tenant.php`
`Route::get('stock-movements', [StockMovementController::class,'index'])->name('stock-movements.index');`
`Route::post('stock-movements', [StockMovementController::class,'store'])->name('stock-movements.store');`

## Tests (TDD — write first)
`tests/Feature/Tenant/StockMovementTest.php`:
- IN increases on-hand + writes a ledger row (reason adjustment, quantity +N).
- OUT decreases on-hand; OUT that would go below 0 → 422 (`assertInvalid('quantity')`), NO ledger row,
  on-hand unchanged.
- ADJUSTMENT sets on-hand to the target (delta recorded = target − current).
- Works for BOTH a product and a raw material (morph map stores `product` / `raw_material`).
- index lists the ledger + returns the picker options.
- guest redirect; validation (bad stockable / missing fields).

## Frontend — `resources/js/pages/tenant/stock-movements/index.tsx`
- `DataTable` ledger: columns date, location, item, quantity (green + / red −), reason, user.
  No row actions (ledger is immutable).
- "New movement" dialog (create-only `ResourceFormDialog` with `editing={null}`): location
  `ComboboxField`; item `ComboboxField` (merged options, hidden `stockable`); a **type** control
  IN / OUT / ADJUSTMENT (shadcn `Select` or segmented, backed by hidden `name="type"`); quantity
  `Input`; notes `Textarea`. For ADJUSTMENT the quantity label reads "Set on-hand to".
- `stockMovementMeta` descriptor (icon `ArrowLeftRight` or `ClipboardList`); add to sidebar nav (Wayfinder).

## Verification
`php artisan test --filter=StockMovement`; `php artisan tenants:migrate`; `bun run types:generate`;
`bun run build`; types/biome; browser smoke (record IN for a product at a location → appears; OUT
beyond stock → error; ADJUSTMENT sets level).
