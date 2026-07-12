<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Models\User;
use App\Services\StockReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant dashboard: period-scoped KPIs + charts (sales vs purchases over time,
 * stock movements by reason) plus current stock alerts. The date range defaults to
 * this week; order/movement figures are filtered to it, the low-stock count is
 * current. All aggregation lives in StockReportService (shared with Reports).
 */
class DashboardController
{
    public function __invoke(Request $request, StockReportService $reports): Response
    {
        $tenant = tenant();

        $from = $request->date('from') ?? Carbon::now()->startOfWeek();
        $to = $request->date('to') ?? Carbon::now()->endOfDay();
        $range = [$from, $to];

        $sales = $reports->salesTotals($range);
        $purchases = $reports->purchaseTotals($range);
        $production = $reports->productionTotals($range);

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
                'sales' => ['count' => $sales->count, 'amount' => $sales->amount],
                'purchases' => ['count' => $purchases->count, 'amount' => $purchases->amount],
                'production' => ['count' => $production->count, 'quantity' => $production->quantity],
                'low_stock' => $reports->lowStockCount(),
            ],
            'series' => $reports->dailySalesPurchases($from, $to),
            'movements' => array_map(
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
