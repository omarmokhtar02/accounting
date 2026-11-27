<?php

use Modules\Accounting\Application\Import\ChartOfAccountsImporter;
use Modules\Accounting\Application\Import\ExchangeRatesImporter;
use Modules\Accounting\Application\Import\ImportCatalog;
use Modules\Accounting\Application\Import\ImportReport;
use Modules\Accounting\Application\Import\JournalsImporter;

covers(ImportCatalog::class, ImportReport::class);

it('reports whether the file had problems', function () {
    expect((new ImportReport(3, 0, 0, [['row' => 2, 'key' => 'x', 'replace' => []]], false))->hasErrors())->toBeTrue()
        ->and((new ImportReport(3, 3, 0, [], true))->hasErrors())->toBeFalse();
});

it('finds every importer by key', function () {
    bootstrapTenant();
    $catalog = app(ImportCatalog::class);

    expect(array_keys($catalog->all()))->toBe(['accounts', 'journals', 'rates'])
        ->and($catalog->find('accounts'))->toBeInstanceOf(ChartOfAccountsImporter::class)
        ->and($catalog->find('journals'))->toBeInstanceOf(JournalsImporter::class)
        ->and($catalog->find('rates'))->toBeInstanceOf(ExchangeRatesImporter::class)
        ->and($catalog->find('nope'))->toBeNull();
});

it('gives every importer a template whose sample rows match its columns and cover the required ones', function () {
    bootstrapTenant();

    foreach (app(ImportCatalog::class)->all() as $importer) {
        expect(array_diff($importer->requiredColumns(), $importer->templateColumns()))->toBe([]);

        foreach ($importer->sampleRows() as $row) {
            expect($row)->toHaveCount(count($importer->templateColumns()));
        }
    }
});
