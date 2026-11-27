<?php

namespace Modules\Accounting\Application\Import;

interface Importer
{
    /** URL-safe identifier, also the lang key suffix. */
    public function key(): string;

    /** @return list<string> columns the file must have */
    public function requiredColumns(): array;

    /** @return list<string> every column the template shows, required first */
    public function templateColumns(): array;

    /** @return list<list<string>> sample rows for the downloadable template */
    public function sampleRows(): array;

    /**
     * Validates every row and, only when $commit is true and nothing failed, writes them all in one transaction.
     *
     * @param  array<int, array<string, string>>  $rows  sheet row number => values by column name
     */
    public function run(array $rows, string $fileHash, bool $commit): ImportReport;
}
