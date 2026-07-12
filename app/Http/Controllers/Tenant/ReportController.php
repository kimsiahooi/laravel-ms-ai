<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Services\StockReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Period-scoped reports. All order/movement figures are filtered to the selected
 * date range; the stock snapshots (low/out-of-stock) are current. Every aggregate
 * comes from StockReportService (shared with the dashboard) — one SQL definition.
 */
class ReportController
{
    public function index(Request $request, StockReportService $reports): Response
    {
        $from = $request->date('from') ?? Carbon::now()->startOfMonth();
        $to = $request->date('to') ?? Carbon::now()->endOfDay();
        $range = [$from, $to];

        // Closures so an `only:` partial reload recomputes only the requested figures.
        return Inertia::render('tenant/reports/index', [
            'filters' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'sales' => function () use ($reports, $range) {
                $sales = $reports->salesTotals($range);

                return ['count' => $sales->count, 'quantity' => $sales->quantity, 'amount' => $sales->amount];
            },
            'purchases' => function () use ($reports, $range) {
                $purchases = $reports->purchaseTotals($range);

                return ['count' => $purchases->count, 'quantity' => $purchases->quantity, 'amount' => $purchases->amount];
            },
            'production' => function () use ($reports, $range) {
                $production = $reports->productionTotals($range);

                return ['count' => $production->count, 'quantity' => $production->quantity];
            },
            'movements' => fn () => array_map(
                fn (array $movement): array => [
                    'reason' => $movement['reason'],
                    'label' => $movement['label'],
                    'count' => $movement['count'],
                    'net_quantity' => $movement['net'],
                ],
                $reports->movementsByReason($range),
            ),
            'lowStock' => fn () => $reports->lowStockRows(),
        ]);
    }
}
