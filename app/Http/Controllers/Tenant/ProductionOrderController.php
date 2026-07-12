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
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\RecipeItem;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

        // Only products with a recipe can be manufactured; ship each one's
        // exploded per-unit needs so the create dialog can preview consumption.
        $manufacturable = Product::has('recipeItems')
            ->with('recipeItems.rawMaterial')
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
            'productRecipes' => $manufacturable->mapWithKeys(fn (Product $product): array => [
                $product->id => $product->recipeItems->map(fn (RecipeItem $item): array => [
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
        $product = Product::with('recipeItems.rawMaterial')->findOrFail($request->integer('product_id'));

        if ($product->recipeItems->isEmpty()) {
            throw ValidationException::withMessages([
                'product_id' => 'This product has no recipe to manufacture from.',
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

            foreach ($product->recipeItems as $item) {
                $order->items()->create([
                    'raw_material_id' => $item->raw_material_id,
                    'raw_material_snapshot' => [
                        'name' => $item->rawMaterial?->name ?? '',
                        'sku' => $item->rawMaterial?->sku ?? '',
                        'unit' => $item->rawMaterial?->unit ?? '',
                    ],
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
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->whereNull('deleted_at')],
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
