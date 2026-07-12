<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Services\StockReportService;
use App\Support\ExportRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a list resource to CSV or Excel via openspout, honouring the current
 * search where the resource supports it. Columns come from ExportRegistry.
 */
class ExportController
{
    public function download(Request $request, string $resource): BinaryFileResponse
    {
        $format = $request->string('format')->toString() === 'xlsx' ? 'xlsx' : 'csv';

        // The Reports page isn't a single-table list — it exports its period-scoped
        // sections (summary + movements + low stock) rather than a registry resource.
        if ($resource === 'reports') {
            return $this->downloadReports($request, app(StockReportService::class), $format);
        }

        $config = ExportRegistry::find($resource);
        abort_if($config === null, 404);

        $search = trim((string) $request->string('search'));
        $columns = $config['columns'];

        return $this->stream($resource, $format, function (WriterInterface $writer) use ($config, $columns, $search): void {
            $writer->addRow(Row::fromValues(array_map(fn (array $c): string => $c['heading'], $columns)));

            foreach (($config['query'])($search)->cursor() as $model) {
                $writer->addRow(Row::fromValues(array_map(
                    fn (array $c): string|int|float => self::cell($c['value']($model)),
                    $columns,
                )));
            }
        });
    }

    /**
     * The Reports export: the same period-scoped figures the screen shows — a summary,
     * the movements-by-reason breakdown, and the current low-stock list — one section per
     * block, honouring the ?from/?to range (defaults to this month, like the page).
     */
    private function downloadReports(Request $request, StockReportService $reports, string $format): BinaryFileResponse
    {
        $from = $request->date('from') ?? Carbon::now()->startOfMonth();
        $to = $request->date('to') ?? Carbon::now()->endOfDay();
        $range = [$from, $to];

        $sales = $reports->salesTotals($range);
        $purchases = $reports->purchaseTotals($range);
        $production = $reports->productionTotals($range);

        return $this->stream('reports', $format, function (WriterInterface $writer) use ($reports, $range, $from, $to, $sales, $purchases, $production): void {
            $writer->addRow(Row::fromValues(['Period', $from->format('Y-m-d'), 'to', $to->format('Y-m-d')]));
            $writer->addRow(Row::fromValues(['']));

            $writer->addRow(Row::fromValues(['Summary', 'Count', 'Quantity', 'Amount']));
            $writer->addRow(Row::fromValues(['Sales', $sales->count, $sales->quantity, $sales->amount]));
            $writer->addRow(Row::fromValues(['Purchases', $purchases->count, $purchases->quantity, $purchases->amount]));
            $writer->addRow(Row::fromValues(['Production', $production->count, $production->quantity, '']));
            $writer->addRow(Row::fromValues(['']));

            $writer->addRow(Row::fromValues(['Stock movements', 'Count', 'Net change']));
            foreach ($reports->movementsByReason($range) as $movement) {
                $writer->addRow(Row::fromValues([$movement['label'], $movement['count'], $movement['net']]));
            }
            $writer->addRow(Row::fromValues(['']));

            $writer->addRow(Row::fromValues(['Low / out of stock — item', 'Warehouse', 'On hand', 'Reorder at', 'Unit']));
            foreach ($reports->lowStockRows() as $row) {
                $writer->addRow(Row::fromValues([$row['item'], $row['warehouse'], $row['on_hand'], $row['reorder_level'], $row['unit']]));
            }
        });
    }

    /**
     * Shared openspout plumbing: open a temp CSV/Excel file, let $writeRows append the
     * rows, then stream it as a dated download that self-deletes after sending.
     *
     * @param  callable(WriterInterface): void  $writeRows
     */
    private function stream(string $resource, string $format, callable $writeRows): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'export');
        $writer = $format === 'xlsx' ? new XlsxWriter : new CsvWriter;
        $writer->openToFile($tmp);

        $writeRows($writer);

        $writer->close();

        $filename = $resource.'-'.now()->format('Y-m-d').'.'.$format;
        $headers = [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv',
        ];

        return response()->download($tmp, $filename, $headers)->deleteFileAfterSend();
    }

    /** openspout cells must be scalar; null → '', booleans → Yes/No, numbers as-is. */
    private static function cell(mixed $value): string|int|float
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_int($value), is_float($value) => $value,
            default => (string) $value,
        };
    }
}
