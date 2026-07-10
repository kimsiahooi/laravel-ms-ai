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
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /** Trailing window (inclusive of today) for the two time-series charts. */
    private const TREND_DAYS = 30;

    public function __invoke(): Response
    {
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
                    'completed_units_30d' => $this->completedUnits(),
                ],
                'skus_in_stock' => [
                    'count' => $inStockProducts + $inStockMaterials,
                    'products' => $inStockProducts,
                    'materials' => $inStockMaterials,
                ],
            ],
            'stockActivity' => $this->stockActivity(),
            'orderPipeline' => [
                'purchase' => $this->statusCounts(PurchaseOrder::class, PurchaseOrderStatus::cases()),
                'sales' => $this->statusCounts(SalesOrder::class, SalesOrderStatus::cases()),
                'production' => $this->statusCounts(ProductionOrder::class, ProductionOrderStatus::cases()),
            ],
            'throughput' => $this->throughput(),
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

    /** Total finished units produced in the trailing window. */
    private function completedUnits(): float
    {
        return (float) ProductionOrder::query()
            ->where('status', ProductionOrderStatus::Completed)
            ->where('completed_at', '>=', $this->windowStart())
            ->get(['quantity'])
            ->sum(fn (ProductionOrder $o): float => (float) $o->quantity);
    }

    /**
     * Units in vs out per day for the trailing window. Bucketed in PHP (not SQL
     * date functions) so it behaves identically on MySQL and the SQLite test DB.
     *
     * @return array<int, array{date: string, label: string, in: float, out: float}>
     */
    private function stockActivity(): array
    {
        $byDay = StockMovement::query()
            ->where('created_at', '>=', $this->windowStart())
            ->get(['quantity', 'created_at'])
            ->groupBy(fn (StockMovement $m): string => $m->created_at->format('Y-m-d'));

        return $this->fillDays(function (CarbonInterface $day) use ($byDay): array {
            $rows = $byDay->get($day->format('Y-m-d'), collect());
            $in = 0.0;
            $out = 0.0;
            foreach ($rows as $m) {
                $q = (float) $m->quantity;
                $q >= 0 ? $in += $q : $out += -$q;
            }

            return ['in' => $in, 'out' => $out];
        });
    }

    /**
     * Finished units produced per day for the trailing window (zero-filled).
     *
     * @return array<int, array{date: string, label: string, units: float}>
     */
    private function throughput(): array
    {
        $byDay = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::Completed)
            ->where('completed_at', '>=', $this->windowStart())
            ->get(['quantity', 'completed_at'])
            ->groupBy(fn (ProductionOrder $o): string => $o->completed_at->format('Y-m-d'));

        return $this->fillDays(fn (CarbonInterface $day): array => [
            'units' => (float) $byDay->get($day->format('Y-m-d'), collect())
                ->sum(fn (ProductionOrder $o): float => (float) $o->quantity),
        ]);
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

    private function windowStart(): CarbonInterface
    {
        return now()->subDays(self::TREND_DAYS - 1)->startOfDay();
    }

    /**
     * Build one row per day across the trailing window (oldest first), merging in
     * the per-day payload so gaps become explicit zeros and the axis is continuous.
     *
     * @param  callable(CarbonInterface): array<string, float>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function fillDays(callable $payload): array
    {
        $out = [];
        for ($i = self::TREND_DAYS - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $out[] = array_merge([
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('M j'),
            ], $payload($day));
        }

        return $out;
    }
}
