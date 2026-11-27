<?php

namespace Modules\Shared\Infrastructure\Import;

use DateTimeInterface;
use Modules\Shared\Domain\Exceptions\InvalidImportFile;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Reads the first sheet of a CSV or XLSX file into rows keyed by lower-cased header name. Row keys are
 * the real sheet row numbers (the header is row 1) so error messages point at the line the user sees.
 * Everything comes back as trimmed strings; dates become Y-m-d.
 */
final class SpreadsheetReader
{
    public const MAX_ROWS = 5000;

    /**
     * @param  list<string>  $required
     * @return array<int, array<string, string>>
     */
    public function read(string $path, string $extension, array $required = []): array
    {
        $reader = $this->open(strtolower($extension));
        $rows = [];

        try {
            $reader->open($path);
            $header = null;
            $number = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $number++;
                    $cells = array_map(fn ($cell) => $this->stringify($cell->getValue()), $row->getCells());

                    if ($header === null) {
                        $header = array_map(fn (string $h) => strtolower(ltrim($h, "\xEF\xBB\xBF")), $cells);
                        $missing = array_values(array_diff($required, $header));

                        if ($missing !== []) {
                            throw InvalidImportFile::missingColumns($missing);
                        }

                        continue;
                    }

                    if (implode('', $cells) === '') {
                        continue;
                    }

                    if (count($rows) >= self::MAX_ROWS) {
                        throw InvalidImportFile::tooManyRows(self::MAX_ROWS);
                    }

                    $rows[$number] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
                }

                break;
            }
        } catch (InvalidImportFile $e) {
            throw $e;
        } catch (Throwable) {
            throw InvalidImportFile::unreadable();
        } finally {
            try {
                $reader->close();
            } catch (Throwable) {
            }
        }

        if ($rows === []) {
            throw InvalidImportFile::empty();
        }

        return $rows;
    }

    private function open(string $extension): ReaderInterface
    {
        return match ($extension) {
            'csv' => new CsvReader(self::keepEmptyRows(new CsvOptions)),
            'xlsx' => new XlsxReader(self::keepEmptyRows(new XlsxOptions)),
            default => throw InvalidImportFile::unreadable(),
        };
    }

    /** Blank lines stay in the sequence so reported row numbers match what the user sees in Excel. */
    private static function keepEmptyRows(CsvOptions|XlsxOptions $options): CsvOptions|XlsxOptions
    {
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return $options;
    }

    private function stringify(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }
}
