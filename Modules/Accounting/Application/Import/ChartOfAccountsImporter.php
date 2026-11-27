<?php

namespace Modules\Accounting\Application\Import;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Enums\AccountType;
use Modules\Accounting\Domain\Enums\CashFlowCategory;
use Modules\Accounting\Domain\Models\Account;
use Modules\Shared\Application\RecordAudit;
use Modules\Tenancy\Infrastructure\TenantContext;

/**
 * Adds accounts from a sheet: code, name, type are required; name_ar, parent_code, postable (yes/no) and
 * cash_flow are optional. Codes that already exist are skipped (never overwritten), so the same file
 * can be uploaded again safely.
 */
final class ChartOfAccountsImporter implements Importer
{
    public function __construct(private readonly TenantContext $context, private readonly RecordAudit $audit) {}

    public function key(): string
    {
        return 'accounts';
    }

    public function requiredColumns(): array
    {
        return ['code', 'name', 'type'];
    }

    public function templateColumns(): array
    {
        return ['code', 'name', 'type', 'name_ar', 'parent_code', 'postable', 'cash_flow'];
    }

    public function sampleRows(): array
    {
        return [
            ['1000', 'Current assets', 'asset', 'الأصول المتداولة', '', 'no', 'none'],
            ['1010', 'Cash', 'asset', 'النقدية', '1000', 'yes', 'cash'],
        ];
    }

    public function run(array $rows, string $fileHash, bool $commit): ImportReport
    {
        $companyId = $this->context->companyId();
        $existing = Account::query()->where('company_id', $companyId)->get()->keyBy('code');
        $errors = [];
        $parsed = [];
        $skipped = 0;

        foreach ($rows as $number => $row) {
            $code = $row['code'] ?? '';
            $type = AccountType::tryFrom(strtolower($row['type'] ?? ''));
            $category = ($row['cash_flow'] ?? '') === '' ? CashFlowCategory::None : CashFlowCategory::tryFrom(strtolower($row['cash_flow']));

            $problem = match (true) {
                $code === '' || strlen($code) > 30 => ['accounting.import.bad_code', []],
                ($row['name'] ?? '') === '' => ['accounting.import.bad_name', []],
                $type === null => ['accounting.import.bad_type', ['value' => $row['type'] ?? '']],
                $category === null => ['accounting.import.bad_cash_flow', ['value' => $row['cash_flow']]],
                array_key_exists($code, $parsed) => ['accounting.import.duplicate_code', ['code' => $code]],
                default => null,
            };

            if ($problem !== null) {
                $errors[] = ['row' => $number, 'key' => $problem[0], 'replace' => $problem[1]];

                continue;
            }

            if ($existing->has($code)) {
                $skipped++;
                $parsed[$code] = null;

                continue;
            }

            $parsed[$code] = ['row' => $number, 'type' => $type, 'category' => $category, 'data' => $row];
        }

        $new = array_filter($parsed);
        $parents = [];

        foreach ($new as $item) {
            if (($item['data']['parent_code'] ?? '') !== '') {
                $parents[$item['data']['parent_code']] = true;
            }
        }

        foreach ($new as $key => $item) {
            $code = (string) $key; // PHP turns numeric string keys into ints
            $parentCode = $item['data']['parent_code'] ?? '';

            if ($parentCode === '') {
                continue;
            }

            if ($parentCode === $code || (! array_key_exists($parentCode, $parsed) && ! $existing->has($parentCode))) {
                $errors[] = ['row' => $item['row'], 'key' => 'accounting.import.unknown_parent', 'replace' => ['code' => $parentCode]];
            } elseif ($existing->has($parentCode) && $existing[$parentCode]->is_postable) {
                $errors[] = ['row' => $item['row'], 'key' => 'accounting.import.parent_postable', 'replace' => ['code' => $parentCode]];
            } elseif ($this->cycles($code, $new)) {
                $errors[] = ['row' => $item['row'], 'key' => 'accounting.import.parent_cycle', 'replace' => ['code' => $code]];
            }
        }

        usort($errors, fn (array $a, array $b) => $a['row'] <=> $b['row']);

        if ($errors !== [] || ! $commit) {
            return new ImportReport(count($rows), 0, $skipped, $errors, false);
        }

        DB::transaction(function () use ($new, $parents, $existing, $companyId): void {
            $ids = $existing->map(fn (Account $account) => $account->id)->all();

            foreach ($this->parentsFirst($new) as $code => $item) {
                $postable = isset($parents[$code]) ? false : $this->yes($item['data']['postable'] ?? 'yes');

                $ids[$code] = Account::create([
                    'company_id' => $companyId,
                    'parent_id' => ($item['data']['parent_code'] ?? '') === '' ? null : $ids[$item['data']['parent_code']],
                    'code' => $code,
                    'name' => $item['data']['name'],
                    'name_ar' => ($item['data']['name_ar'] ?? '') ?: null,
                    'type' => $item['type'],
                    'is_postable' => $postable,
                    'cash_flow_category' => $item['category'],
                ])->id;
            }

            $this->audit->handle('company', $companyId, 'accounts_imported', null, ['accounts' => count($new)]);
        });

        return new ImportReport(count($rows), count($new), $skipped, [], true);
    }

    private function yes(string $value): bool
    {
        return ! in_array(strtolower($value), ['no', 'n', '0', 'false'], true);
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     */
    private function cycles(string $code, array $items): bool
    {
        $seen = [];

        for ($c = $code; isset($items[$c]) && ($items[$c]['data']['parent_code'] ?? '') !== ''; $c = $items[$c]['data']['parent_code']) {
            if (isset($seen[$c])) {
                return true;
            }
            $seen[$c] = true;
        }

        return false;
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function parentsFirst(array $items): array
    {
        $depth = function (string $code) use ($items): int {
            $d = 0;
            for ($c = $code; isset($items[$c]) && ($items[$c]['data']['parent_code'] ?? '') !== ''; $c = $items[$c]['data']['parent_code']) {
                $d++;
            }

            return $d;
        };

        uksort($items, fn (string $a, string $b) => $depth($a) <=> $depth($b));

        return $items;
    }
}
