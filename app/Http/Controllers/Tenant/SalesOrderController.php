<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\FulfillSalesOrder;
use App\Data\OptionData;
use App\Data\SalesOrderData;
use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\SalesOrderRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController
{
    use BuildsStockPickers;
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $perPage = $this->perPage($request);

        $orders = SalesOrder::query()
            ->with(['customer', 'items'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SalesOrder $order): SalesOrderData => SalesOrderData::from($order));

        return Inertia::render('tenant/sales-orders/index', [
            'orders' => $orders,
            'customers' => OptionData::collect(Customer::orderBy('name')->get(['id', 'name'])),
            'products' => OptionData::collect(Product::orderBy('name')->get(['id', 'name'])),
            'warehouses' => $this->stockWarehouseOptions(),
            'filters' => [
                'search' => '',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(SalesOrder $salesOrder): Response
    {
        $salesOrder->load(['customer', 'items']);

        return Inertia::render('tenant/sales-orders/show', [
            'order' => SalesOrderData::from($salesOrder),
        ]);
    }

    public function store(SalesOrderRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $order = SalesOrder::create([
                'customer_id' => $request->integer('customer_id'),
                'currency' => strtoupper((string) $request->input('currency')),
                'notes' => $request->input('notes'),
                'user_id' => $request->user()?->id,
                'status' => SalesOrderStatus::Pending,
            ]);

            $this->syncItems($order, $request->array('items'));
        });

        $this->toast('Sales order created.');

        return back();
    }

    public function update(SalesOrderRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        abort_unless($salesOrder->status === SalesOrderStatus::Pending, 422);

        DB::transaction(function () use ($request, $salesOrder): void {
            $salesOrder->update([
                'customer_id' => $request->integer('customer_id'),
                'currency' => strtoupper((string) $request->input('currency')),
                'notes' => $request->input('notes'),
            ]);

            $salesOrder->items()->delete();
            $this->syncItems($salesOrder, $request->array('items'));
        });

        $this->toast('Sales order updated.');

        return back();
    }

    public function destroy(SalesOrder $salesOrder): RedirectResponse
    {
        $salesOrder->delete();

        $this->toast('Sales order deleted.');

        return back();
    }

    public function fulfill(Request $request, SalesOrder $salesOrder, FulfillSalesOrder $action): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->whereNull('deleted_at')],
        ]);

        try {
            $action->handle(
                $salesOrder,
                Warehouse::findOrFail($validated['warehouse_id']),
                $request->user(),
            );
        } catch (InsufficientStockException) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Not enough stock at this warehouse to fulfill the order.',
            ]);
        }

        $this->toast('Sales order fulfilled.');

        return back();
    }

    public function cancel(SalesOrder $salesOrder): RedirectResponse
    {
        abort_unless($salesOrder->status === SalesOrderStatus::Pending, 422);

        $salesOrder->update(['status' => SalesOrderStatus::Cancelled]);

        $this->toast('Sales order cancelled.');

        return back();
    }

    /**
     * (Re)create the order's line items, snapshotting each product's name/sku/unit.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(SalesOrder $order, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);

            $order->items()->create([
                'product_id' => $product?->id,
                'product_snapshot' => [
                    'name' => $product?->name ?? '',
                    'sku' => $product?->sku ?? '',
                    'unit' => $product?->unit ?? '',
                ],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);
        }
    }
}
