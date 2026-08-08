<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryValuationService;
use App\Services\StockReportService;
use App\Settings\BusinessSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant dashboard. Two kinds of number, deliberately kept in separate props:
 * `kpis` is period-scoped (sales / purchases / production over the chosen range,
 * defaulting to this week) and `snapshot` is how things stand right now (stock
 * value, what's low, what's on its way, what's waiting on someone). Only the
 * range-scoped props are refetched when the date filter changes, so changing the
 * range never re-runs the current-state queries. Period aggregation lives in
 * StockReportService (shared with Reports); stock value in InventoryValuationService.
 */
class DashboardController
{
    public function __invoke(
        Request $request,
        StockReportService $reports,
        InventoryValuationService $valuation,
    ): Response {
        $tenant = tenant();

        $from = $request->date('from') ?? Carbon::now()->startOfWeek();
        $to = $request->date('to') ?? Carbon::now()->endOfDay();
        $range = [$from, $to];

        // Every heavy prop is a closure, so an `only:` partial reload (the date-range
        // filter refetches just filters/kpis/series/movements) never recomputes the
        // props it didn't ask for — e.g. the organization/member count is skipped.
        return Inertia::render('tenant/dashboard', [
            'organization' => fn () => [
                'name' => $tenant->name,
                'slug' => $tenant->getKey(),
                'logo' => $tenant->logo,
                'members' => User::count(),
            ],
            'filters' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            // Period-scoped: recomputed whenever the date range changes.
            'kpis' => function () use ($reports, $range) {
                $sales = $reports->salesTotals($range);
                $purchases = $reports->purchaseTotals($range);
                $production = $reports->productionTotals($range);

                return [
                    // The base currency the totals roll up to (business settings). Foreign
                    // orders are converted with their own exchange rate (R05).
                    'currency' => (string) (app(BusinessSettings::class)->values()['default_currency'] ?? 'MYR'),
                    'sales' => ['count' => $sales->count, 'amount' => $sales->amount],
                    'purchases' => ['count' => $purchases->count, 'amount' => $purchases->amount],
                    'production' => ['count' => $production->count, 'quantity' => $production->quantity],
                ];
            },
            // How things stand right now — independent of the date range, so this is
            // left out of the range reload and each query runs once per page load.
            'snapshot' => function () use ($reports, $valuation) {
                $stockValue = $valuation->onHand();
                $incoming = $reports->openPurchaseTotals();
                $pipeline = $reports->productionPipeline();

                return [
                    // What the stock is worth, and how many items that figure can't cover
                    // (no purchase history to derive a cost from) so the card can say so.
                    'stock_value' => [
                        'amount' => $stockValue->value,
                        'valued' => $stockValue->valued,
                        'unvalued' => $stockValue->unvalued,
                    ],
                    'incoming' => [
                        'count' => $incoming->count,
                        'amount' => $incoming->amount,
                        'overdue' => $incoming->overdue,
                    ],
                    'production' => [
                        'active' => $pipeline->active,
                        'blocked' => $pipeline->blocked,
                    ],
                    'low_stock' => $reports->lowStockCount(),
                    'out_of_stock' => $reports->outOfStockCount(),
                    // What's waiting on someone today. Counts only — the page owns the
                    // wording and the link through to each underlying list.
                    'ready_sales' => $reports->salesOrdersReadyToShip(),
                ];
            },
            // Setup progress for the first-run onboarding checklist. Cheap EXISTS
            // probes, and a closure so the date-range partial reload never reruns
            // them — they only matter on a full page load.
            'onboarding' => fn (): array => [
                'location' => Location::query()->exists(),
                'warehouse' => Warehouse::query()->exists(),
                'catalog' => RawMaterial::query()->exists() && Product::query()->exists(),
                'bom' => Product::query()->has('bomItems')->exists(),
                'stock' => StockMovement::query()->exists(),
                'order' => PurchaseOrder::query()->exists() || SalesOrder::query()->exists(),
            ],
            'series' => fn () => $reports->dailySalesPurchases($from, $to),
            'movements' => fn () => array_map(
                fn (array $movement): array => [
                    'reason' => $movement['reason'],
                    'label' => $movement['label'],
                    'net' => $movement['net'],
                ],
                $reports->movementsByReason($range),
            ),
        ]);
    }
}
