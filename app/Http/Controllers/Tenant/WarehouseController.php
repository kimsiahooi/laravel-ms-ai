<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\OptionData;
use App\Data\WarehouseData;
use App\Data\WarehouseItemData;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\WarehouseRequest;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController
{
    use ResolvesPerPage;
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $perPage = $this->perPage($request);

        $warehouses = Warehouse::query()
            ->with('location')
            ->search($search)
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Warehouse $warehouse): WarehouseData {
                // Per-warehouse stock summary for the list badges, from the same
                // union the detail page uses so the numbers reconcile. One small
                // count query per listed warehouse (a page's worth).
                $counts = $this->stockCounts($warehouse->id);
                $data = WarehouseData::from($warehouse);
                $data->items_in_stock = $counts['in_stock'];
                $data->low_stock = $counts['low'];
                $data->out_of_stock = $counts['out'];

                return $data;
            });

        return Inertia::render('tenant/warehouses/index', [
            'warehouses' => $warehouses,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'locations' => OptionData::collect(Location::orderBy('name')->get(['id', 'name'])),
        ]);
    }

    public function show(Request $request, Warehouse $warehouse): Response
    {
        $warehouse->load('location');

        $search = trim((string) $request->string('search'));
        $view = (string) $request->string('view');
        $perPage = $this->perPage($request);
        $like = '%'.$search.'%';
        $whId = $warehouse->id;

        $items = DB::query()->fromSub($this->warehouseItemsUnion($whId), 'items')
            // Default view = in stock OR alerting, so out-of-stock alerts (0 < min)
            // still show. Grouped so the search-OR below ANDs onto the whole thing.
            ->when($view !== 'all', fn ($q) => $q->where(fn ($g) => $g
                ->where('on_hand', '>', 0)
                ->orWhere(fn ($r) => $r->where('min_stock', '>', 0)
                    ->whereColumn('on_hand', '<', 'min_stock'))))
            ->when($search !== '', fn ($q) => $q->where(fn ($g) => $g
                ->where('item', 'like', $like)->orWhere('sku', 'like', $like)))
            ->orderByDesc('on_hand')
            ->orderBy('stockable_type')  // deterministic tiebreaker — (type, id) is
            ->orderBy('stockable_id')    // unique across the two UNION legs
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (object $row) => WarehouseItemData::fromRow($row));

        // Full-set counts from the same union, so the summary reconciles with the
        // list by construction. needs_reorder = low + out (both below the level).
        $counts = $this->stockCounts($whId);

        return Inertia::render('tenant/warehouses/show', [
            'warehouse' => WarehouseData::from($warehouse),
            'items' => $items,
            'summary' => [
                'in_stock' => $counts['in_stock'],
                'needs_reorder' => $counts['low'] + $counts['out'],
            ],
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'view' => $view,
            ],
        ]);
    }

    /**
     * One row per catalog item (product + raw material) with THIS warehouse's
     * on_hand + min_stock left-joined in. Two morph tables → a two-leg UNION ALL;
     * predicates live in the JOIN ON (an outer where would null out the LEFT JOIN
     * and drop unstocked items). Trashed items are excluded per leg. Shared by the
     * detail item list and the counts so both read the identical row set.
     */
    private function warehouseItemsUnion(int $whId): Builder
    {
        $leg = fn (string $table, string $alias) => DB::table($table)
            ->selectRaw("
                '{$alias}' as stockable_type, {$table}.id as stockable_id,
                {$table}.name as item, {$table}.sku as sku, {$table}.unit as unit,
                COALESCE(ws.quantity, 0) as on_hand,
                COALESCE(rl.min_stock, 0) as min_stock
            ")
            ->whereNull("{$table}.deleted_at")
            ->leftJoin('warehouse_stocks as ws', fn ($j) => $j
                ->on('ws.stockable_id', '=', "{$table}.id")
                ->where('ws.stockable_type', $alias)
                ->where('ws.warehouse_id', $whId))
            ->leftJoin('warehouse_reorder_levels as rl', fn ($j) => $j
                ->on('rl.stockable_id', '=', "{$table}.id")
                ->where('rl.stockable_type', $alias)
                ->where('rl.warehouse_id', $whId));

        return $leg('products', 'product')->unionAll($leg('raw_materials', 'raw_material'));
    }

    /**
     * Full-set stock counts for one warehouse: in_stock (on-hand > 0), low (below
     * the reorder level but not empty), out (reorder level set, nothing on hand).
     * low + out equals the detail page's "needs reorder".
     *
     * @return array{in_stock: int, low: int, out: int}
     */
    private function stockCounts(int $whId): array
    {
        $counts = DB::query()->fromSub($this->warehouseItemsUnion($whId), 'items')
            ->selectRaw('
                SUM(CASE WHEN on_hand > 0 THEN 1 ELSE 0 END) as in_stock,
                SUM(CASE WHEN min_stock > 0 AND on_hand > 0 AND on_hand < min_stock THEN 1 ELSE 0 END) as low_count,
                SUM(CASE WHEN min_stock > 0 AND on_hand = 0 THEN 1 ELSE 0 END) as out_count
            ')
            ->first();

        return [
            'in_stock' => (int) ($counts->in_stock ?? 0),
            'low' => (int) ($counts->low_count ?? 0),
            'out' => (int) ($counts->out_count ?? 0),
        ];
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        Warehouse::create($request->validated());

        $this->toast('Warehouse created.');

        return back();
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        $this->toast('Warehouse updated.');

        return back();
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $warehouse->delete();

        $this->toast('Warehouse deleted.');

        return back();
    }
}
