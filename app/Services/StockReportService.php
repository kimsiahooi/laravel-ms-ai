<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\WarehouseReorderLevel;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The single home for period-scoped sales / purchase / production / movement
 * aggregates and the cross-warehouse low-stock query — shared by the Reports page
 * and the dashboard so the SQL lives in exactly one place. Order/movement figures are
 * filtered to a [from, to] range; the low-stock views are a current snapshot. Amounts
 * are order-line totals (quantity × unit price/cost). Statuses come from the enums,
 * never magic strings, so renaming a case can't silently zero a total.
 */
class StockReportService
{
    /**
     * @param  array{0: CarbonInterface, 1: CarbonInterface}  $range
     * @return object{count: int, quantity: float, amount: float}
     */
    public function salesTotals(array $range): object
    {
        $row = DB::table('sales_orders')
            ->join('sales_order_items', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->where('sales_orders.status', SalesOrderStatus::Fulfilled->value)
            ->whereNull('sales_orders.deleted_at')
            ->whereBetween('sales_orders.fulfilled_at', $range)
            ->selectRaw('COUNT(DISTINCT sales_orders.id) as cnt, COALESCE(SUM(sales_order_items.quantity), 0) as qty, COALESCE(SUM(sales_order_items.quantity * sales_order_items.unit_price), 0) as amount')
            ->first();

        return (object) ['count' => (int) $row->cnt, 'quantity' => (float) $row->qty, 'amount' => (float) $row->amount];
    }

    /**
     * @param  array{0: CarbonInterface, 1: CarbonInterface}  $range
     * @return object{count: int, quantity: float, amount: float}
     */
    public function purchaseTotals(array $range): object
    {
        $row = DB::table('purchase_orders')
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.status', PurchaseOrderStatus::Received->value)
            ->whereNull('purchase_orders.deleted_at')
            ->whereBetween('purchase_orders.received_at', $range)
            ->selectRaw('COUNT(DISTINCT purchase_orders.id) as cnt, COALESCE(SUM(purchase_order_items.quantity), 0) as qty, COALESCE(SUM(purchase_order_items.quantity * purchase_order_items.unit_cost), 0) as amount')
            ->first();

        return (object) ['count' => (int) $row->cnt, 'quantity' => (float) $row->qty, 'amount' => (float) $row->amount];
    }

    /**
     * @param  array{0: CarbonInterface, 1: CarbonInterface}  $range
     * @return object{count: int, quantity: float}
     */
    public function productionTotals(array $range): object
    {
        $row = DB::table('production_orders')
            ->where('status', ProductionOrderStatus::Completed->value)
            ->whereNull('deleted_at')
            ->whereBetween('completed_at', $range)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(quantity), 0) as qty')
            ->first();

        return (object) ['count' => (int) $row->cnt, 'quantity' => (float) $row->qty];
    }

    /**
     * Net stock change by reason over the range, labelled and with a movement count.
     *
     * @param  array{0: CarbonInterface, 1: CarbonInterface}  $range
     * @return array<int, array{reason: string, label: string, count: int, net: float}>
     */
    public function movementsByReason(array $range): array
    {
        return DB::table('stock_movements')
            ->whereBetween('created_at', $range)
            ->groupBy('reason')
            ->selectRaw('reason, COUNT(*) as cnt, COALESCE(SUM(quantity), 0) as net')
            ->orderBy('reason')
            ->get()
            ->map(fn (object $row): array => [
                'reason' => $row->reason,
                'label' => StockMovementReason::from($row->reason)->label(),
                'count' => (int) $row->cnt,
                'net' => (float) $row->net,
            ])
            ->all();
    }

    /**
     * A continuous per-day {day, label, sales, purchases} series across [from, to],
     * gaps filled with 0 so a chart line stays unbroken. Capped at ~1 year of days.
     *
     * @return array<int, array{day: string, label: string, sales: float, purchases: float}>
     */
    public function dailySalesPurchases(CarbonInterface $from, CarbonInterface $to): array
    {
        $range = [$from, $to];

        $salesByDay = DB::table('sales_orders')
            ->join('sales_order_items', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->where('sales_orders.status', SalesOrderStatus::Fulfilled->value)
            ->whereNull('sales_orders.deleted_at')
            ->whereBetween('sales_orders.fulfilled_at', $range)
            ->selectRaw('DATE(sales_orders.fulfilled_at) as day, COALESCE(SUM(sales_order_items.quantity * sales_order_items.unit_price), 0) as amount')
            ->groupBy('day')
            ->pluck('amount', 'day');

        $purchasesByDay = DB::table('purchase_orders')
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.status', PurchaseOrderStatus::Received->value)
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
            // Reassign (not in-place) so this advances whether the app's date class
            // is mutable Carbon or CarbonImmutable (Date::use(CarbonImmutable::class)).
            $cursor = $cursor->addDay();
            $guard++;
        }

        return $series;
    }

    /** Current count of items at or below their reorder level, across all warehouses. */
    public function lowStockCount(): int
    {
        return WarehouseReorderLevel::query()->belowLevel()->count();
    }

    /**
     * Current items at or below their reorder level, on-hand ascending, with the item
     * name/unit resolved and a "{location} · {warehouse}" label.
     *
     * @return array<int, array{warehouse: string, item: string, unit: string, on_hand: float, reorder_level: float}>
     */
    public function lowStockRows(): array
    {
        $rows = WarehouseReorderLevel::query()
            ->belowLevel()
            ->leftJoin('locations as loc', 'loc.id', '=', 'w.location_id')
            ->selectRaw('w.name as wh_name, loc.name as loc_name, warehouse_reorder_levels.stockable_type, warehouse_reorder_levels.stockable_id, COALESCE(ws.quantity, 0) as on_hand, warehouse_reorder_levels.min_stock as reorder_level')
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
