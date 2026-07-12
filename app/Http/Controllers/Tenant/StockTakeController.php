<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\PostStockTake;
use App\Data\StockTakeData;
use App\Enums\StockTakeStatus;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\StockTakeRequest;
use App\Models\StockTake;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockTakeController
{
    use BuildsStockPickers;
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        $takes = $this->paginateList(
            StockTake::query()
                ->with(['warehouse.location', 'items'])
                ->search($search)
                ->latest()
                ->latest('id'),
            $perPage,
        )->through(fn (StockTake $take): StockTakeData => StockTakeData::from($take));

        return Inertia::render('tenant/stock-takes/index', [
            'takes' => $takes,
            'warehouses' => $this->stockWarehouseOptions(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(StockTake $stockTake): Response
    {
        $stockTake->load(['warehouse.location', 'items']);

        return Inertia::render('tenant/stock-takes/show', [
            'take' => StockTakeData::from($stockTake),
        ]);
    }

    public function store(StockTakeRequest $request): RedirectResponse
    {
        $warehouse = Warehouse::findOrFail($request->integer('warehouse_id'));

        $take = DB::transaction(function () use ($request, $warehouse): StockTake {
            $take = StockTake::create([
                'warehouse_id' => $warehouse->id,
                'status' => StockTakeStatus::Draft,
                'user_id' => $request->user()?->id,
                'notes' => $request->input('notes'),
            ]);

            // Snapshot the warehouse's current items + on-hand as the starting count.
            $stocks = WarehouseStock::where('warehouse_id', $warehouse->id)
                ->with('stockable')
                ->get();

            foreach ($stocks as $stock) {
                $stockable = $stock->stockable;

                if ($stockable === null) {
                    continue;
                }

                $take->items()->create([
                    'stockable_type' => $stockable->getMorphClass(),
                    'stockable_id' => $stockable->getKey(),
                    'stockable_snapshot' => [
                        'name' => $stockable->name,
                        'sku' => $stockable->sku ?? null,
                        'unit' => $stockable->unit ?? '',
                    ],
                    'system_qty' => (float) $stock->quantity,
                    'counted_qty' => (float) $stock->quantity,
                    'variance' => 0,
                ]);
            }

            return $take;
        });

        $this->toast('Stock take started.');

        return redirect()->route('tenant.stock-takes.show', [
            'tenant' => tenant('id'),
            'stockTake' => $take->id,
        ]);
    }

    public function post(Request $request, StockTake $stockTake, PostStockTake $action): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.counted_qty' => ['required', 'numeric', 'min:0'],
        ]);

        $action->handle($stockTake, $validated['items'] ?? [], $request->user());

        $this->toast('Stock count applied.');

        return redirect()->route('tenant.stock-takes.show', [
            'tenant' => tenant('id'),
            'stockTake' => $stockTake->id,
        ]);
    }

    public function cancel(StockTake $stockTake): RedirectResponse
    {
        abort_unless(
            $stockTake->status === StockTakeStatus::Draft,
            422,
            'Only a draft stock take can be cancelled.',
        );

        $stockTake->update(['status' => StockTakeStatus::Cancelled]);

        $this->toast('Stock take cancelled.');

        return back();
    }

    public function destroy(StockTake $stockTake): RedirectResponse
    {
        $stockTake->delete();

        $this->toast('Stock take deleted.');

        return redirect()->route('tenant.stock-takes.index', ['tenant' => tenant('id')]);
    }
}
