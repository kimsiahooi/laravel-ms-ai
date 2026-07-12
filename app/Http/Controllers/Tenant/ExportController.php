<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Support\ExportRegistry;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
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
        $config = ExportRegistry::find($resource);
        abort_if($config === null, 404);

        $format = $request->string('format')->toString() === 'xlsx' ? 'xlsx' : 'csv';
        $search = trim((string) $request->string('search'));
        $columns = $config['columns'];

        $tmp = tempnam(sys_get_temp_dir(), 'export');
        $writer = $format === 'xlsx' ? new XlsxWriter : new CsvWriter;
        $writer->openToFile($tmp);

        $writer->addRow(Row::fromValues(array_map(fn (array $c): string => $c['heading'], $columns)));

        foreach (($config['query'])($search)->cursor() as $model) {
            $writer->addRow(Row::fromValues(array_map(
                fn (array $c): string|int|float => self::cell($c['value']($model)),
                $columns,
            )));
        }

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
