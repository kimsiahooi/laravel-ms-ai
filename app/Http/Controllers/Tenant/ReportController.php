<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Period-scoped reports. All order/movement figures are filtered to the selected
 * date range; the stock snapshots (low/out-of-stock) are current. Amounts are
 * quantity + order-line based (valuation/COGS waits on costing).
 */
class ReportController
{
    public function index(Request $request): Response
    {
        $from = $request->date('from') ?? Carbon::now()->startOfMonth();
        $to = $request->date('to') ?? Carbon::now()->endOfDay();
        $range = [$from, $to];

        $sales = DB::table('sales_orders')
            ->join('sales_order_items', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->where('sales_orders.status', 'fulfilled')
            ->whereNull('sales_orders.deleted_at')
            ->whereBetween('sales_orders.fulfilled_at', $range)
            ->selectRaw('COUNT(DISTINCT sales_orders.id) as cnt, COALESCE(SUM(sales_order_items.quantity), 0) as qty, COALESCE(SUM(sales_order_items.quantity * sales_order_items.unit_price), 0) as amount')
            ->first();

        $purchases = DB::table('purchase_orders')
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.status', 'received')
            ->whereNull('purchase_orders.deleted_at')
            ->whereBetween('purchase_orders.received_at', $range)
            ->selectRaw('COUNT(DISTINCT purchase_orders.id) as cnt, COALESCE(SUM(purchase_order_items.quantity), 0) as qty, COALESCE(SUM(purchase_order_items.quantity * purchase_order_items.unit_cost), 0) as amount')
            ->first();

        $production = DB::table('production_orders')
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->whereBetween('completed_at', $range)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(quantity), 0) as qty')
            ->first();

        $movements = DB::table('stock_movements')
            ->whereBetween('created_at', $range)
            ->groupBy('reason')
            ->selectRaw('reason, COUNT(*) as cnt, COALESCE(SUM(quantity), 0) as net')
            ->orderBy('reason')
            ->get()
            ->map(fn (object $row): array => [
                'reason' => $row->reason,
                'label' => StockMovementReason::from($row->reason)->label(),
                'count' => (int) $row->cnt,
                'net_quantity' => (float) $row->net,
            ])
            ->all();

        return Inertia::render('tenant/reports/index', [
            'filters' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'sales' => [
                'count' => (int) $sales->cnt,
                'quantity' => (float) $sales->qty,
                'amount' => (float) $sales->amount,
            ],
            'purchases' => [
                'count' => (int) $purchases->cnt,
                'quantity' => (float) $purchases->qty,
                'amount' => (float) $purchases->amount,
            ],
            'production' => [
                'count' => (int) $production->cnt,
                'quantity' => (float) $production->qty,
            ],
            'movements' => $movements,
            'lowStock' => $this->lowStock(),
        ]);
    }

    /**
     * Current items at or below their reorder level, across all warehouses.
     *
     * @return array<int, array<string, mixed>>
     */
    private function lowStock(): array
    {
        $rows = DB::table('warehouse_reorder_levels as rl')
            ->join('warehouses as w', 'w.id', '=', 'rl.warehouse_id')
            ->leftJoin('locations as loc', 'loc.id', '=', 'w.location_id')
            ->leftJoin('warehouse_stocks as ws', function ($join) {
                $join->on('ws.warehouse_id', '=', 'rl.warehouse_id')
                    ->on('ws.stockable_type', '=', 'rl.stockable_type')
                    ->on('ws.stockable_id', '=', 'rl.stockable_id');
            })
            ->whereNull('w.deleted_at')
            ->where('rl.min_stock', '>', 0)
            ->whereRaw('COALESCE(ws.quantity, 0) <= rl.min_stock')
            ->selectRaw('w.name as wh_name, loc.name as loc_name, rl.stockable_type, rl.stockable_id, COALESCE(ws.quantity, 0) as on_hand, rl.min_stock as reorder_level')
            ->orderBy('on_hand')
            ->get();

        $products = Product::whereIn('id', $rows->where('stockable_type', 'product')->pluck('stockable_id'))
            ->get(['id', 'name', 'unit'])->keyBy('id');
        $raws = RawMaterial::whereIn('id', $rows->where('stockable_type', 'raw_material')->pluck('stockable_id'))
            ->get(['id', 'name', 'unit'])->keyBy('id');

        return $rows->map(function (object $row) use ($products, $raws): array {
            $item = $row->stockable_type === 'product'
                ? ($products[$row->stockable_id] ?? null)
                : ($raws[$row->stockable_id] ?? null);

            return [
                'warehouse' => ($row->loc_name ?? '?').' · '.$row->wh_name,
                'item' => $item->name ?? '—',
                'unit' => $item->unit ?? '',
                'on_hand' => (float) $row->on_hand,
                'reorder_level' => (float) $row->reorder_level,
            ];
        })->values()->all();
    }
}
