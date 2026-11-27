<?php

namespace Modules\Accounting\Application\Import;

/**
 * The importers a company can use, looked up by their URL key. Adding an importer is one line here.
 */
final class ImportCatalog
{
    /** @var array<string, Importer> */
    private array $importers = [];

    public function __construct(ChartOfAccountsImporter $accounts, JournalsImporter $journals, ExchangeRatesImporter $rates)
    {
        foreach ([$accounts, $journals, $rates] as $importer) {
            $this->importers[$importer->key()] = $importer;
        }
    }

    /**
     * @return array<string, Importer>
     */
    public function all(): array
    {
        return $this->importers;
    }

    public function find(string $key): ?Importer
    {
        return $this->importers[$key] ?? null;
    }
}
