<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\StockMovementData;
use App\Enums\ProductionOrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Models\LocationStock;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /** Default trailing window (inclusive of today) for the time-series charts. */
    private const TREND_DAYS = 30;

    /** Hard cap on a hand-edited range so the per-day scaffold can't run away. */
    private const MAX_RANGE_DAYS = 366;

    public function __invoke(Request $request): Response
    {
        // The two time-series charts + the "units made" figure follow a
        // user-selected date range, resolved in the caller's OWN timezone (sent
        // by the browser). Everything else on the dashboard is a live snapshot.
        [$from, $to, $tz, $days] = $this->resolveRange($request);

        // On-hand summed per stockable, split by morph alias. Reused by the
        // low-stock KPI, the reorder list, and the SKU count. Never-stocked items
        // simply have no row here, which is exactly "0 on hand".
        $onHandByProduct = $this->onHandMap('product');
        $onHandByMaterial = $this->onHandMap('raw_material');

        $lowStock = $this->lowStock($onHandByProduct, $onHandByMaterial);

        // In-stock SKU split, derived from the same on-hand maps so the headline
        // count always reconciles with its products/materials sub-parts.
        $inStockProducts = $onHandByProduct->filter(fn (float $qty): bool => $qty > 0)->count();
        $inStockMaterials = $onHandByMaterial->filter(fn (float $qty): bool => $qty > 0)->count();

        return Inertia::render('tenant/dashboard', [
            'kpis' => [
                'open_documents' => [
                    'total' => $this->pending(PurchaseOrder::class, PurchaseOrderStatus::Pending)
                        + $this->pending(SalesOrder::class, SalesOrderStatus::Pending)
                        + $this->pending(ProductionOrder::class, ProductionOrderStatus::Pending),
                    'purchase' => $this->pending(PurchaseOrder::class, PurchaseOrderStatus::Pending),
                    'sales' => $this->pending(SalesOrder::class, SalesOrderStatus::Pending),
                    'production' => $this->pending(ProductionOrder::class, ProductionOrderStatus::Pending),
                ],
                'low_stock' => [
                    'count' => $lowStock->count(),
                    'out_of_stock' => $lowStock->filter(fn (array $r): bool => $r['on_hand'] <= 0)->count(),
                ],
                'production_in_progress' => [
                    'pending' => $this->pending(ProductionOrder::class, ProductionOrderStatus::Pending),
                ],
                'skus_in_stock' => [
                    'count' => $inStockProducts + $inStockMaterials,
                    'products' => $inStockProducts,
                    'materials' => $inStockMaterials,
                ],
            ],
            'range' => [
                'from' => $days[0]['date'],
                'to' => $days[array_key_last($days)]['date'],
                'units_made' => $this->completedUnits($from, $to),
            ],
            'stockActivity' => $this->stockActivity($from, $to, $tz, $days),
            'orderPipeline' => [
                'purchase' => $this->statusCounts(PurchaseOrder::class, PurchaseOrderStatus::cases()),
                'sales' => $this->statusCounts(SalesOrder::class, SalesOrderStatus::cases()),
                'production' => $this->statusCounts(ProductionOrder::class, ProductionOrderStatus::cases()),
            ],
            'throughput' => $this->throughput($from, $to, $tz, $days),
            'onHandByWarehouse' => $this->onHandByWarehouse(),
            'reorderList' => $lowStock->sortByDesc('deficit')->take(8)->values()->all(),
            'recentMovements' => StockMovement::query()
                ->with(['location.warehouse', 'stockable', 'user'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (StockMovement $m): StockMovementData => StockMovementData::from($m))
                ->all(),
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function pending(string $model, \BackedEnum $status): int
    {
        return $model::query()->where('status', $status)->count();
    }

    /**
     * Counts keyed by every status value of the enum (0 for statuses with none),
     * so the front-end can render a stable donut without guessing keys.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, \BackedEnum>  $cases
     * @return array<string, int>
     */
    private function statusCounts(string $model, array $cases): array
    {
        $counts = $model::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $out = [];
        foreach ($cases as $case) {
            $out[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $out;
    }

    /**
     * On-hand quantity summed per stockable id for one morph alias. Stock sitting
     * in a soft-deleted location or warehouse is excluded — the same rule the
     * "On-hand by warehouse" chart uses — so every on-hand surface agrees.
     *
     * @return Collection<int, float> keyed by stockable_id
     */
    private function onHandMap(string $type): Collection
    {
        return LocationStock::query()
            ->join('locations', 'locations.id', '=', 'location_stocks.location_id')
            ->join('warehouses', 'warehouses.id', '=', 'locations.warehouse_id')
            ->whereNull('locations.deleted_at')
            ->whereNull('warehouses.deleted_at')
            ->where('location_stocks.stockable_type', $type)
            ->groupBy('location_stocks.stockable_id')
            ->selectRaw('location_stocks.stockable_id as stockable_id, SUM(location_stocks.quantity) as qty')
            ->pluck('qty', 'stockable_id')
            ->map(fn ($qty): float => (float) $qty);
    }

    /**
     * Products + raw materials whose on-hand is below their (positive) reorder
     * point, each as a flat row with its shortfall.
     *
     * @param  Collection<int, float>  $onHandByProduct
     * @param  Collection<int, float>  $onHandByMaterial
     * @return Collection<int, array{type: string, id: int, name: string, sku: string, on_hand: float, min_stock: float, deficit: float}>
     */
    private function lowStock(Collection $onHandByProduct, Collection $onHandByMaterial): Collection
    {
        $rows = function (string $type, iterable $items, Collection $onHand): array {
            $out = [];
            foreach ($items as $item) {
                $have = (float) ($onHand[$item->id] ?? 0);
                $min = (float) $item->min_stock;
                if ($have < $min) {
                    $out[] = [
                        'type' => $type,
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'on_hand' => $have,
                        'min_stock' => $min,
                        'deficit' => $min - $have,
                    ];
                }
            }

            return $out;
        };

        return collect($rows(
            'product',
            Product::where('min_stock', '>', 0)->get(['id', 'name', 'sku', 'min_stock']),
            $onHandByProduct,
        ))->concat($rows(
            'raw_material',
            RawMaterial::where('min_stock', '>', 0)->get(['id', 'name', 'sku', 'min_stock']),
            $onHandByMaterial,
        ))->values();
    }

    /** Total finished units produced within the range. */
    private function completedUnits(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) ProductionOrder::query()
            ->where('status', ProductionOrderStatus::Completed)
            ->whereBetween('completed_at', [$from->utc(), $to->utc()])
            ->get(['quantity'])
            ->sum(fn (ProductionOrder $o): float => (float) $o->quantity);
    }

    /**
     * Units in vs out per day across the range. Rows are filtered by UTC instants
     * (timestamps are stored UTC) but bucketed by the caller's LOCAL day, so a day
     * on the chart matches the user's own calendar. Bucketing is done in PHP (not
     * SQL date functions) so it behaves identically on MySQL and the SQLite tests.
     *
     * @param  array<int, array{date: string, label: string}>  $days
     * @return array<int, array{date: string, label: string, in: float, out: float}>
     */
    private function stockActivity(CarbonInterface $from, CarbonInterface $to, string $tz, array $days): array
    {
        $byDay = StockMovement::query()
            ->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->get(['quantity', 'created_at'])
            ->groupBy(fn (StockMovement $m): string => $m->created_at->setTimezone($tz)->format('Y-m-d'));

        return array_map(function (array $day) use ($byDay): array {
            $rows = $byDay->get($day['date'], collect());
            $in = 0.0;
            $out = 0.0;
            foreach ($rows as $m) {
                $q = (float) $m->quantity;
                $q >= 0 ? $in += $q : $out += -$q;
            }

            return array_merge($day, ['in' => $in, 'out' => $out]);
        }, $days);
    }

    /**
     * Finished units produced per (local) day across the range, zero-filled.
     *
     * @param  array<int, array{date: string, label: string}>  $days
     * @return array<int, array{date: string, label: string, units: float}>
     */
    private function throughput(CarbonInterface $from, CarbonInterface $to, string $tz, array $days): array
    {
        $byDay = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::Completed)
            ->whereBetween('completed_at', [$from->utc(), $to->utc()])
            ->get(['quantity', 'completed_at'])
            ->groupBy(fn (ProductionOrder $o): string => $o->completed_at->setTimezone($tz)->format('Y-m-d'));

        return array_map(fn (array $day): array => array_merge($day, [
            'units' => (float) $byDay->get($day['date'], collect())
                ->sum(fn (ProductionOrder $o): float => (float) $o->quantity),
        ]), $days);
    }

    /**
     * On-hand units summed per warehouse (trashed warehouses/locations excluded).
     *
     * @return array<int, array{name: string, quantity: float}>
     */
    private function onHandByWarehouse(): array
    {
        return LocationStock::query()
            ->join('locations', 'locations.id', '=', 'location_stocks.location_id')
            ->join('warehouses', 'warehouses.id', '=', 'locations.warehouse_id')
            ->whereNull('locations.deleted_at')
            ->whereNull('warehouses.deleted_at')
            ->groupBy('warehouses.id', 'warehouses.name')
            ->selectRaw('warehouses.name as name, SUM(location_stocks.quantity) as quantity')
            ->orderByDesc('quantity')
            ->get()
            ->map(fn ($row): array => [
                'name' => (string) $row->name,
                'quantity' => (float) $row->quantity,
            ])
            ->all();
    }

    /**
     * Resolve the requested date range in the caller's timezone. `from`/`to` are
     * local YYYY-MM-DD dates and `tz` is the device's IANA zone; all default to a
     * trailing 30-day window in the app timezone. Returns the local start/end
     * instants, the resolved timezone, and a zero-filled per-day scaffold.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface, 2: string, 3: array<int, array{date: string, label: string}>}
     */
    private function resolveRange(Request $request): array
    {
        $tz = $this->safeTimezone($request->string('tz')->toString());
        $today = Date::now($tz)->startOfDay();

        $from = $this->parseDate($request->string('from')->toString(), $tz)
            ?? $today->subDays(self::TREND_DAYS - 1);
        $to = $this->parseDate($request->string('to')->toString(), $tz) ?? $today;

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $from = $from->startOfDay();
        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $from = $to->subDays(self::MAX_RANGE_DAYS)->startOfDay();
        }
        $to = $to->endOfDay();

        $days = [];
        for ($cursor = $from; $cursor->lte($to); $cursor = $cursor->addDay()) {
            $days[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('M j'),
            ];
        }

        return [$from, $to, $tz, $days];
    }

    /** A validated IANA timezone, falling back to the app timezone. */
    private function safeTimezone(string $tz): string
    {
        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return config('app.timezone') ?: 'UTC';
    }

    /** Parse a YYYY-MM-DD date in the given zone, or null if it isn't one. */
    private function parseDate(string $value, string $tz): ?CarbonInterface
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Date::createFromFormat('!Y-m-d', $value, $tz);
        } catch (\Throwable) {
            return null;
        }
    }
}
