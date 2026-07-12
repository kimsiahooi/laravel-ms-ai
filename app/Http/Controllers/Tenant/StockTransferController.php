<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\StockTransferData;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\StockTransferRequest;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController
{
    use BuildsStockPickers;
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        $transfers = StockTransfer::query()
            ->with(['fromWarehouse.location', 'toWarehouse.location', 'stockable', 'user'])
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (StockTransfer $transfer): StockTransferData => StockTransferData::from($transfer));

        return Inertia::render('tenant/stock-transfers/index', [
            'transfers' => $transfers,
            'warehouses' => $this->stockWarehouseOptions(),
            'items' => $this->stockItemOptions(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Filter the transfer ledger by item name/sku, notes, or either endpoint's
     * warehouse code / warehouse name.
     *
     * @param  Builder<StockTransfer>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';

        $endpoint = fn (Builder $warehouse) => $warehouse
            ->where('code', 'like', $like)
            ->orWhereHas('location', fn (Builder $location) => $location->where('name', 'like', $like));

        $query->where(function (Builder $group) use ($like, $endpoint): void {
            $group
                ->where('notes', 'like', $like)
                ->orWhereHasMorph(
                    'stockable',
                    [Product::class, RawMaterial::class],
                    fn (Builder $item) => $item->where('name', 'like', $like)->orWhere('sku', 'like', $like),
                )
                ->orWhereHas('fromWarehouse', $endpoint)
                ->orWhereHas('toWarehouse', $endpoint);
        });
    }

    public function store(StockTransferRequest $request, StockService $service): RedirectResponse
    {
        $stockable = $this->resolveStockable((string) $request->input('stockable'));

        $from = Warehouse::findOrFail($request->integer('from_warehouse_id'));
        $to = Warehouse::findOrFail($request->integer('to_warehouse_id'));

        try {
            $service->transfer(
                $from,
                $to,
                $stockable,
                (float) $request->input('quantity'),
                $request->user(),
                $request->input('notes'),
            );
        } catch (InsufficientStockException) {
            throw ValidationException::withMessages([
                'quantity' => 'Not enough stock at the source warehouse.',
            ]);
        }

        $this->toast('Transfer recorded.');

        return back();
    }
}
