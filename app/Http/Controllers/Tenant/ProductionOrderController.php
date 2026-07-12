<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\CompleteProductionOrder;
use App\Data\OptionData;
use App\Data\ProductionOrderData;
use App\Enums\ProductionOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\ProductionOrderRequest;
use App\Models\BomItem;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use App\Support\ActiveExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductionOrderController
{
    use BuildsStockPickers;
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $perPage = $this->perPage($request);

        $orders = ProductionOrder::query()
            ->with(['product', 'items'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ProductionOrder $order): ProductionOrderData => ProductionOrderData::from($order));

        // Only products with a BOM can be manufactured; ship each one's
        // exploded per-unit needs so the create dialog can preview consumption.
        $manufacturable = Product::has('bomItems')
            ->with('bomItems.rawMaterial')
            ->orderBy('name')
            ->get();

        return Inertia::render('tenant/production-orders/index', [
            'orders' => $orders,
            'products' => OptionData::collect(
                $manufacturable->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                ]),
            ),
            'productBoms' => $manufacturable->mapWithKeys(fn (Product $product): array => [
                $product->id => $product->bomItems->map(fn (BomItem $item): array => [
                    'name' => $item->rawMaterial?->name ?? '—',
                    'quantity' => (float) $item->quantity,
                ])->all(),
            ])->all(),
            'warehouses' => $this->stockWarehouseOptions(),
            'filters' => [
                'search' => '',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(ProductionOrder $productionOrder): Response
    {
        $productionOrder->load(['product', 'items']);

        return Inertia::render('tenant/production-orders/show', [
            'order' => ProductionOrderData::from($productionOrder),
        ]);
    }

    public function store(ProductionOrderRequest $request): RedirectResponse
    {
        $product = Product::with('bomItems.rawMaterial')->findOrFail($request->integer('product_id'));

        if ($product->bomItems->isEmpty()) {
            throw ValidationException::withMessages([
                'product_id' => 'This product has no BOM to manufacture from.',
            ]);
        }

        $quantity = (float) $request->input('quantity');

        DB::transaction(function () use ($request, $product, $quantity): void {
            $order = ProductionOrder::create([
                'product_id' => $product->id,
                'product_snapshot' => [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit,
                ],
                'quantity' => $quantity,
                'notes' => $request->input('notes'),
                'user_id' => $request->user()?->id,
                'status' => ProductionOrderStatus::Pending,
            ]);

            foreach ($product->bomItems as $item) {
                $order->items()->create([
                    'raw_material_id' => $item->raw_material_id,
                    'raw_material_snapshot' => RawMaterial::snapshotOf($item->rawMaterial),
                    'quantity_per_unit' => $item->quantity,
                    'quantity_required' => (float) $item->quantity * $quantity,
                ]);
            }
        });

        $this->toast('Production order created.');

        return back();
    }

    public function destroy(ProductionOrder $productionOrder): RedirectResponse
    {
        $productionOrder->delete();

        $this->toast('Production order deleted.');

        return back();
    }

    public function complete(Request $request, ProductionOrder $productionOrder, CompleteProductionOrder $action): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', ActiveExists::of('warehouses')],
        ]);

        try {
            $action->handle(
                $productionOrder,
                Warehouse::findOrFail($validated['warehouse_id']),
                $request->user(),
            );
        } catch (InsufficientStockException) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Not enough raw material at this warehouse to complete this order.',
            ]);
        }

        $this->toast('Production order completed.');

        return back();
    }

    public function cancel(ProductionOrder $productionOrder): RedirectResponse
    {
        abort_unless($productionOrder->status === ProductionOrderStatus::Pending, 422);

        $productionOrder->update(['status' => ProductionOrderStatus::Cancelled]);

        $this->toast('Production order cancelled.');

        return back();
    }
}
