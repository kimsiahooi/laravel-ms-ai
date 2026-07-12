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
use App\Http\Requests\Tenant\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\Warehouse;
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

    public function index(Request $request): Response
    {
        $perPage = $this->perPage($request);

        $orders = PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order): PurchaseOrderData => PurchaseOrderData::from($order));

        return Inertia::render('tenant/purchase-orders/index', [
            'orders' => $orders,
            'suppliers' => OptionData::collect(Supplier::orderBy('name')->get(['id', 'name'])),
            'rawMaterials' => OptionData::collect(RawMaterial::orderBy('name')->get(['id', 'name'])),
            'warehouses' => $this->stockWarehouseOptions(),
            'filters' => [
                'search' => '',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['supplier', 'items']);

        return Inertia::render('tenant/purchase-orders/show', [
            'order' => PurchaseOrderData::from($purchaseOrder),
        ]);
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $order = PurchaseOrder::create([
                'supplier_id' => $request->integer('supplier_id'),
                'currency' => strtoupper((string) $request->input('currency')),
                'notes' => $request->input('notes'),
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

        DB::transaction(function () use ($request, $purchaseOrder): void {
            $purchaseOrder->update([
                'supplier_id' => $request->integer('supplier_id'),
                'currency' => strtoupper((string) $request->input('currency')),
                'notes' => $request->input('notes'),
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
                'raw_material_snapshot' => [
                    'name' => $rawMaterial?->name ?? '',
                    'sku' => $rawMaterial?->sku ?? '',
                    'unit' => $rawMaterial?->unit ?? '',
                ],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
            ]);
        }
    }
}
