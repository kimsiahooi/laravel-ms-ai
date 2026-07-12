<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\StockMovementReason;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant dashboard: period-scoped KPIs + charts (sales vs purchases over time,
 * stock movements by reason) plus current stock alerts. The date range defaults to
 * this week; order/movement figures are filtered to it, the low-stock count is
 * current. Amounts are order-line totals (quantity × price/cost).
 */
class DashboardController
{
    public function __invoke(Request $request): Response
    {
        $tenant = tenant();

        $from = $request->date('from') ?? Carbon::now()->startOfWeek();
        $to = $request->date('to') ?? Carbon::now()->endOfDay();
        $range = [$from, $to];

        $sales = DB::table('sales_orders')
            ->join('sales_order_items', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->where('sales_orders.status', 'fulfilled')
            ->whereNull('sales_orders.deleted_at')
            ->whereBetween('sales_orders.fulfilled_at', $range)
            ->selectRaw('COUNT(DISTINCT sales_orders.id) as cnt, COALESCE(SUM(sales_order_items.quantity * sales_order_items.unit_price), 0) as amount')
            ->first();

        $purchases = DB::table('purchase_orders')
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.status', 'received')
            ->whereNull('purchase_orders.deleted_at')
            ->whereBetween('purchase_orders.received_at', $range)
            ->selectRaw('COUNT(DISTINCT purchase_orders.id) as cnt, COALESCE(SUM(purchase_order_items.quantity * purchase_order_items.unit_cost), 0) as amount')
            ->first();

        $production = DB::table('production_orders')
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->whereBetween('completed_at', $range)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(quantity), 0) as qty')
            ->first();

        // Current items at/below their reorder level, across all warehouses.
        $lowStock = DB::table('warehouse_reorder_levels as rl')
            ->join('warehouses as w', 'w.id', '=', 'rl.warehouse_id')
            ->leftJoin('warehouse_stocks as ws', function ($join) {
                $join->on('ws.warehouse_id', '=', 'rl.warehouse_id')
                    ->on('ws.stockable_type', '=', 'rl.stockable_type')
                    ->on('ws.stockable_id', '=', 'rl.stockable_id');
            })
            ->whereNull('w.deleted_at')
            ->where('rl.min_stock', '>', 0)
            ->whereRaw('COALESCE(ws.quantity, 0) < rl.min_stock')
            ->count();

        return Inertia::render('tenant/dashboard', [
            'organization' => [
                'name' => $tenant->name,
                'slug' => $tenant->getKey(),
                'logo' => $tenant->logo,
                'members' => User::count(),
            ],
            'filters' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'kpis' => [
                'sales' => ['count' => (int) $sales->cnt, 'amount' => (float) $sales->amount],
                'purchases' => ['count' => (int) $purchases->cnt, 'amount' => (float) $purchases->amount],
                'production' => ['count' => (int) $production->cnt, 'quantity' => (float) $production->qty],
                'low_stock' => $lowStock,
            ],
            'series' => $this->dailySeries($from, $to),
            'movements' => $this->movementsByReason($range),
        ]);
    }

    /**
     * A continuous per-day {day, label, sales, purchases} series across the range,
     * gaps filled with 0 so the chart line is unbroken. Capped at ~1 year of days.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dailySeries(Carbon $from, Carbon $to): array
    {
        $range = [$from, $to];

        $salesByDay = DB::table('sales_orders')
            ->join('sales_order_items', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->where('sales_orders.status', 'fulfilled')
            ->whereNull('sales_orders.deleted_at')
            ->whereBetween('sales_orders.fulfilled_at', $range)
            ->selectRaw('DATE(sales_orders.fulfilled_at) as day, COALESCE(SUM(sales_order_items.quantity * sales_order_items.unit_price), 0) as amount')
            ->groupBy('day')
            ->pluck('amount', 'day');

        $purchasesByDay = DB::table('purchase_orders')
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.status', 'received')
            ->whereNull('purchase_orders.deleted_at')
            ->whereBetween('purchase_orders.received_at', $range)
            ->selectRaw('DATE(purchase_orders.received_at) as day, COALESCE(SUM(purchase_order_items.quantity * purchase_order_items.unit_cost), 0) as amount')
            ->groupBy('day')
            ->pluck('amount', 'day');

        $series = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $guard = 0;

        while ($cursor->lte($end) && $guard < 366) {
            $key = $cursor->format('Y-m-d');
            $series[] = [
                'day' => $key,
                'label' => $cursor->format('M j'),
                'sales' => (float) ($salesByDay[$key] ?? 0),
                'purchases' => (float) ($purchasesByDay[$key] ?? 0),
            ];
            $cursor->addDay();
            $guard++;
        }

        return $series;
    }

    /**
     * @param  array{0: Carbon, 1: Carbon}  $range
     * @return array<int, array<string, mixed>>
     */
    private function movementsByReason(array $range): array
    {
        return DB::table('stock_movements')
            ->whereBetween('created_at', $range)
            ->groupBy('reason')
            ->selectRaw('reason, COALESCE(SUM(quantity), 0) as net')
            ->orderBy('reason')
            ->get()
            ->map(fn (object $row): array => [
                'reason' => $row->reason,
                'label' => StockMovementReason::from($row->reason)->label(),
                'net' => (float) $row->net,
            ])
            ->all();
    }
}
