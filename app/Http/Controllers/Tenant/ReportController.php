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

        $sales = $reports->salesTotals($range);
        $purchases = $reports->purchaseTotals($range);
        $production = $reports->productionTotals($range);

        return Inertia::render('tenant/reports/index', [
            'filters' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'sales' => [
                'count' => $sales->count,
                'quantity' => $sales->quantity,
                'amount' => $sales->amount,
            ],
            'purchases' => [
                'count' => $purchases->count,
                'quantity' => $purchases->quantity,
                'amount' => $purchases->amount,
            ],
            'production' => [
                'count' => $production->count,
                'quantity' => $production->quantity,
            ],
            'movements' => array_map(
                fn (array $movement): array => [
                    'reason' => $movement['reason'],
                    'label' => $movement['label'],
                    'count' => $movement['count'],
                    'net_quantity' => $movement['net'],
                ],
                $reports->movementsByReason($range),
            ),
            'lowStock' => $reports->lowStockRows(),
        ]);
    }
}
