<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\CompleteSalesReturn;
use App\Data\OptionData;
use App\Data\SalesReturnData;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\SalesReturnRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\Warehouse;
use App\Support\ActiveExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesReturnController
{
    use BuildsStockPickers;
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $perPage = $this->perPage($request);

        $returns = SalesReturn::query()
            ->with(['customer', 'items'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SalesReturn $return): SalesReturnData => SalesReturnData::from($return));

        return Inertia::render('tenant/sales-returns/index', [
            'returns' => $returns,
            'customers' => OptionData::collect(Customer::orderBy('name')->get(['id', 'name'])),
            'products' => OptionData::collect(Product::orderBy('name')->get(['id', 'name'])),
            'warehouses' => $this->stockWarehouseOptions(),
            'filters' => [
                'search' => '',
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(SalesReturn $salesReturn): Response
    {
        $salesReturn->load(['customer', 'items']);

        return Inertia::render('tenant/sales-returns/show', [
            'return' => SalesReturnData::from($salesReturn),
        ]);
    }

    public function store(SalesReturnRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $return = SalesReturn::create([
                'customer_id' => $request->integer('customer_id') ?: null,
                'notes' => $request->input('notes'),
                'user_id' => $request->user()?->id,
                'status' => ReturnStatus::Pending,
            ]);

            $this->syncItems($return, $request->array('items'));
        });

        $this->toast('Sales return created.');

        return back();
    }

    public function update(SalesReturnRequest $request, SalesReturn $salesReturn): RedirectResponse
    {
        abort_unless($salesReturn->status === ReturnStatus::Pending, 422);

        DB::transaction(function () use ($request, $salesReturn): void {
            $salesReturn->update([
                'customer_id' => $request->integer('customer_id') ?: null,
                'notes' => $request->input('notes'),
            ]);

            $salesReturn->items()->delete();
            $this->syncItems($salesReturn, $request->array('items'));
        });

        $this->toast('Sales return updated.');

        return back();
    }

    public function destroy(SalesReturn $salesReturn): RedirectResponse
    {
        $salesReturn->delete();

        $this->toast('Sales return deleted.');

        return back();
    }

    public function complete(Request $request, SalesReturn $salesReturn, CompleteSalesReturn $action): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', ActiveExists::of('warehouses')],
        ]);

        $action->handle(
            $salesReturn,
            Warehouse::findOrFail($validated['warehouse_id']),
            $request->user(),
        );

        $this->toast('Sales return completed.');

        return back();
    }

    public function cancel(SalesReturn $salesReturn): RedirectResponse
    {
        abort_unless($salesReturn->status === ReturnStatus::Pending, 422);

        $salesReturn->update(['status' => ReturnStatus::Cancelled]);

        $this->toast('Sales return cancelled.');

        return back();
    }

    /**
     * (Re)create the return's line items, snapshotting each product's name/sku/unit.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(SalesReturn $return, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);

            $return->items()->create([
                'product_id' => $product?->id,
                'product_snapshot' => [
                    'name' => $product?->name ?? '',
                    'sku' => $product?->sku ?? '',
                    'unit' => $product?->unit ?? '',
                ],
                'quantity' => $item['quantity'],
            ]);
        }
    }
}
