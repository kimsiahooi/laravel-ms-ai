<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\ReceivePurchaseOrder;
use App\Data\OptionData;
use App\Data\PurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Settings\BusinessSettings;
use App\Support\ActiveExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController
{
    use BuildsStockPickers;
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        $query = PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->search($search);
        $sort = $this->applySort($query, $request, ['id', 'status', 'created_at']);

        $baseCurrency = app(BusinessSettings::class)->baseCurrency();

        $orders = $this->paginateList($query, $perPage)
            ->through(fn (PurchaseOrder $order): PurchaseOrderData => PurchaseOrderData::fromPurchaseOrder($order, $baseCurrency));

        return Inertia::render('tenant/purchase-orders/index', [
            'orders' => $orders,
            // The order form picks a currency + FX rate; new orders default to base.
            'baseCurrency' => $baseCurrency,
            'currencies' => BusinessSettings::currencies(),
            'suppliers' => OptionData::collect(Supplier::orderBy('name')->get(['id', 'name'])),
            'rawMaterials' => OptionData::collect(RawMaterial::orderBy('name')->get(['id', 'name'])),
            'warehouses' => $this->stockWarehouseOptions(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sort['sort'],
                'direction' => $sort['direction'],
            ],
        ]);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['supplier', 'items']);

        return Inertia::render('tenant/purchase-orders/show', [
            'order' => PurchaseOrderData::from($purchaseOrder),
            'warehouses' => fn () => $this->stockWarehouseOptions(),
            'print' => $request->boolean('print'),
        ]);
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $currency = strtoupper((string) $request->input('currency'));

        DB::transaction(function () use ($request, $currency): void {
            $order = PurchaseOrder::create([
                'supplier_id' => $request->integer('supplier_id'),
                'currency' => $currency,
                'exchange_rate' => $this->exchangeRateFor($currency, $request->float('exchange_rate')),
                'number' => $request->input('number'),
                'notes' => $request->input('notes'),
                'expected_date' => $request->date('expected_date'),
                'user_id' => $request->user()?->id,
                'status' => PurchaseOrderStatus::Pending,
            ]);

            $this->syncItems($order, $request->array('items'));
        });

        $this->toast('Purchase order created.');

        return back();
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->status === PurchaseOrderStatus::Pending, 422);

        $currency = strtoupper((string) $request->input('currency'));

        DB::transaction(function () use ($request, $purchaseOrder, $currency): void {
            $purchaseOrder->update([
                'supplier_id' => $request->integer('supplier_id'),
                'currency' => $currency,
                'exchange_rate' => $this->exchangeRateFor($currency, $request->float('exchange_rate')),
                'number' => $request->input('number'),
                'notes' => $request->input('notes'),
                'expected_date' => $request->date('expected_date'),
            ]);

            $purchaseOrder->items()->delete();
            $this->syncItems($purchaseOrder, $request->array('items'));
        });

        $this->toast('Purchase order updated.');

        return back();
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->delete();

        $this->toast('Purchase order deleted.');

        return back();
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, ReceivePurchaseOrder $action): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', ActiveExists::of('warehouses')],
        ]);

        $action->handle(
            $purchaseOrder,
            Warehouse::findOrFail($validated['warehouse_id']),
            $request->user(),
        );

        $this->toast('Purchase order received.');

        return back();
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->status === PurchaseOrderStatus::Pending, 422);

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Cancelled]);

        $this->toast('Purchase order cancelled.');

        return back();
    }

    /**
     * The rate to store: 1 for a base-currency order (or a missing/zero rate), else
     * the entered rate. The server is the source of truth for the base case.
     */
    private function exchangeRateFor(string $currency, float $rate): float
    {
        if ($currency === app(BusinessSettings::class)->baseCurrency()) {
            return 1.0;
        }

        return $rate > 0 ? $rate : 1.0;
    }

    /**
     * (Re)create the order's line items, snapshotting each raw material's name/sku/unit.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(PurchaseOrder $order, array $items): void
    {
        foreach ($items as $item) {
            $rawMaterial = RawMaterial::find($item['raw_material_id']);

            $order->items()->create([
                'raw_material_id' => $rawMaterial?->id,
                'raw_material_snapshot' => RawMaterial::snapshotOf($rawMaterial),
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
            ]);
        }
    }
}
