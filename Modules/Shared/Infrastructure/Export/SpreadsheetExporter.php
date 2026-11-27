<?php

namespace Modules\Shared\Infrastructure\Export;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams tabular data to the browser as CSV or XLSX (openspout writes row by row, so large exports
 * do not load the whole file in memory). The first row is the header, shown in bold in Excel.
 * Cells given as int/float stay numeric in Excel so the user can sum and filter them.
 */
final class SpreadsheetExporter
{
    public const FORMATS = ['csv', 'xlsx'];

    /**
     * @param  list<string>  $header
     * @param  iterable<list<string|int|float|null>>  $rows
     */
    public function download(string $format, string $filename, array $header, iterable $rows): StreamedResponse
    {
        abort_unless(in_array($format, self::FORMATS, true), 404);

        $isXlsx = $format === 'xlsx';
        $contentType = $isXlsx ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8';
        $filename = ltrim(preg_replace('/[^A-Za-z0-9_.-]+/', '_', $filename), '.').'.'.$format;

        return new StreamedResponse(function () use ($isXlsx, $header, $rows): void {
            $writer = $isXlsx ? new XlsxWriter : new CsvWriter;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues($header, (new Style)->setFontBold()));

            foreach ($rows as $row) {
                $writer->addRow(new Row(array_map(fn ($value) => Cell::fromValue($value), $row)));
            }

            $writer->close();
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
