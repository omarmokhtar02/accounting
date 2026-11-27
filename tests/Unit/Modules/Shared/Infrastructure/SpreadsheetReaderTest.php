<?php

use Modules\Shared\Domain\Exceptions\InvalidImportFile;
use Modules\Shared\Infrastructure\Export\SpreadsheetExporter;
use Modules\Shared\Infrastructure\Import\SpreadsheetReader;

covers(SpreadsheetReader::class);

function tempFile(string $content, string $extension): string
{
    $path = tempnam(sys_get_temp_dir(), 'imp').'.'.$extension;
    file_put_contents($path, $content);

    return $path;
}

it('reads a CSV into rows keyed by lower-cased header, using real sheet row numbers', function () {
    $path = tempFile("\xEF\xBB\xBFCode,Name\n1010,Cash\n\n1020, Bank \n", 'csv');

    expect((new SpreadsheetReader)->read($path, 'csv', ['code', 'name']))
        ->toBe([2 => ['code' => '1010', 'name' => 'Cash'], 4 => ['code' => '1020', 'name' => 'Bank']]);
});

it('reads an XLSX written by the exporter', function () {
    ob_start();
    app(SpreadsheetExporter::class)->download('xlsx', 'x', ['code', 'amount'], [['1010', 12.5], ['1020', 3]])->sendContent();
    $path = tempFile((string) ob_get_clean(), 'xlsx');

    expect((new SpreadsheetReader)->read($path, 'XLSX', ['code']))
        ->toBe([2 => ['code' => '1010', 'amount' => '12.5'], 3 => ['code' => '1020', 'amount' => '3']]);
});

it('pads short rows and ignores extra cells', function () {
    $path = tempFile("a,b\n1\n1,2,3\n", 'csv');

    expect((new SpreadsheetReader)->read($path, 'csv'))->toBe([2 => ['a' => '1', 'b' => ''], 3 => ['a' => '1', 'b' => '2']]);
});

it('names the missing required columns', function () {
    (new SpreadsheetReader)->read(tempFile("a,b\n1,2\n", 'csv'), 'csv', ['a', 'c']);
})->throws(InvalidImportFile::class, 'app.import.missing_columns');

it('rejects an empty file', function () {
    (new SpreadsheetReader)->read(tempFile("a,b\n", 'csv'), 'csv');
})->throws(InvalidImportFile::class, 'app.import.file_empty');

it('rejects unsupported or corrupt files', function (string $extension, string $content) {
    (new SpreadsheetReader)->read(tempFile($content, $extension), $extension);
})->with([['xls', 'x'], ['xlsx', 'not a zip']])->throws(InvalidImportFile::class, 'app.import.file_unreadable');

it('refuses files with too many rows', function () {
    $rows = "a\n".str_repeat("1\n", SpreadsheetReader::MAX_ROWS + 1);

    (new SpreadsheetReader)->read(tempFile($rows, 'csv'), 'csv');
})->throws(InvalidImportFile::class, 'app.import.too_many_rows');
