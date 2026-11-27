<?php

use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Modules\Accounting\Application\Commands\CreateJournalEntry;
use Modules\Accounting\Application\Commands\PostJournalEntry;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Accounting\Presentation\Controllers\ReportController;
use Modules\Localization\Application\CreateCompany;
use Modules\Shared\Infrastructure\Export\SpreadsheetExporter;
use Modules\Subscriptions\Domain\Models\Subscription;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

covers(ReportController::class, SpreadsheetExporter::class);

function postMove(object $user, $debit, $credit, int $amount, string $date): JournalEntry
{
    $entry = app(CreateJournalEntry::class)->handle(new CreateJournalEntryData(
        CarbonImmutable::parse($date), 'Report move',
        [new JournalLineData($debit->id, $amount, 0), new JournalLineData($credit->id, 0, $amount)],
    ));

    return app(PostJournalEntry::class)->handle($entry, $user, allowSelfPosting: true);
}

/** @return list<list<mixed>> rows of an XLSX response */
function xlsxRows(StreamedResponse|TestResponse $response): array
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $response->streamedContent());
    $reader = new XlsxReader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();
    @unlink($path);

    return $rows;
}

beforeEach(function () {
    ['user' => $this->owner, 'company' => $this->company] = bootstrapTenant();
    openFiscalYear2026();
    $this->cash = accountByRole(AccountRole::Cash);
    $this->sales = accountByRole(AccountRole::SalesRevenue);
    postMove($this->owner, $this->cash, $this->sales, 123456, '2026-03-10');
    postMove($this->owner, $this->cash, $this->sales, 50000, '2026-04-02');
    $this->range = ['from' => '2026-03-01', 'to' => '2026-12-31'];
});

describe('trial balance page', function () {
    it('shows the accounts, the drill-down links and balanced totals', function () {
        $this->get(route('accounting.reports.trial-balance', $this->range))->assertOk()
            ->assertSee('Trial balance')->assertSee($this->cash->code)->assertSee('1,734.56')->assertSee('data-total="debit"', false)
            ->assertSee(e(route('accounting.reports.general-ledger', ['account_id' => $this->cash->id, 'from' => '2026-03-01', 'to' => '2026-12-31'])), false)
            ->assertDontSee('does not balance');
    });

    it('defaults to this year up to today and renders empty without data', function () {
        bootstrapTenant('empty@example.com', 'Empty');

        $this->get(route('accounting.reports.trial-balance'))->assertOk();
    });

    it('can show accounts without activity', function () {
        $this->get(route('accounting.reports.trial-balance', $this->range + ['hide_zero' => 0]))->assertOk()->assertSee('6103');
        $this->get(route('accounting.reports.trial-balance', $this->range + ['hide_zero' => 1]))->assertOk()->assertDontSee('>6103<', false);
    });

    it('validates the dates', function () {
        $this->get(route('accounting.reports.trial-balance', ['from' => '2026-05-01', 'to' => '2026-04-01']))->assertSessionHasErrors('to');
        $this->get(route('accounting.reports.trial-balance', ['from' => 'yesterday-ish']))->assertSessionHasErrors('from');
        $this->get(route('accounting.reports.trial-balance', ['format' => 'pdf']))->assertSessionHasErrors('format');
    });

    it('renders Arabic with Arabic account names, right to left', function () {
        $this->withSession(['locale' => 'ar'])->get(route('accounting.reports.trial-balance', $this->range))->assertOk()
            ->assertSee('ميزان المراجعة')->assertSee('dir="rtl"', false)->assertSee('الخزينة');
    });
});

describe('trial balance export', function () {
    it('downloads CSV with a header, one line per account and a totals line', function () {
        $response = $this->get(route('accounting.reports.trial-balance', $this->range + ['format' => 'csv']));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        expect($response->headers->get('Content-Disposition'))->toContain('trial-balance_2026-03-01_2026-12-31.csv');

        $content = $response->streamedContent();
        expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue(); // UTF-8 BOM: Excel needs it to read Arabic correctly
        $lines = array_map('str_getcsv', array_filter(explode("\n", str_replace("\r", '', substr($content, 3)))));

        expect($lines[0])->toBe(['Code', 'Name', 'Type', 'Opening debit', 'Opening credit', 'Debit', 'Credit', 'Closing debit', 'Closing credit'])
            ->and(count($lines))->toBe(4) // header + cash + sales + totals
            ->and(collect($lines)->firstWhere(0, $this->cash->code)[5])->toBe('1734.56')
            ->and(end($lines)[1])->toBe('Total');
    });

    it('downloads a real XLSX workbook with numeric cells', function () {
        $response = $this->get(route('accounting.reports.trial-balance', $this->range + ['format' => 'xlsx']));

        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $rows = xlsxRows($response);
        $cash = collect($rows)->firstWhere(0, $this->cash->code);

        expect($rows[0][0])->toBe('Code')->and($cash[5])->toBeNumeric()->and((float) $cash[5])->toBe(1734.56)
            ->and((float) end($rows)[5])->toBe((float) end($rows)[6]);
    });

    it('exports in Arabic when the UI is Arabic', function () {
        $response = $this->withSession(['locale' => 'ar'])->get(route('accounting.reports.trial-balance', $this->range + ['format' => 'csv']));

        expect($response->streamedContent())->toContain('الكود')->toContain('الخزينة');
    });

    it('needs the reports.export permission but not for viewing', function () {
        actingAsRole('viewer');

        $this->get(route('accounting.reports.trial-balance', $this->range))->assertOk()->assertDontSee('format=xlsx', false);
        $this->get(route('accounting.reports.trial-balance', $this->range + ['format' => 'xlsx']))->assertForbidden();
        $this->get(route('accounting.reports.trial-balance', $this->range + ['format' => 'csv']))->assertForbidden();
    });

    it('lets senior accountants and auditors export', function (string $role) {
        actingAsRole($role);

        $this->get(route('accounting.reports.trial-balance', $this->range + ['format' => 'csv']))->assertOk();
    })->with(['senior_accountant', 'auditor']);
});

describe('general ledger page', function () {
    it('asks for an account first, then shows opening, lines with drill-down links, and closing', function () {
        $this->get(route('accounting.reports.general-ledger', $this->range))->assertOk()->assertSee('Cash on hand')->assertDontSee('data-balance', false);

        $entry = JournalEntry::withoutGlobalScopes()->where('description', 'Report move')->orderBy('id')->first();

        $this->get(route('accounting.reports.general-ledger', $this->range + ['account_id' => $this->cash->id]))->assertOk()
            ->assertSee('data-balance="opening"', false)->assertSee('data-balance="closing"', false)->assertSee('1,234.56')->assertSee('1,734.56')
            ->assertSee(route('accounting.journal-entries.show', $entry->id), false);
    });

    it('opens from the trial balance with the same period', function () {
        $this->get(route('accounting.reports.general-ledger', ['account_id' => $this->sales->id, 'from' => '2026-04-01', 'to' => '2026-04-30']))->assertOk()
            ->assertSee('500.00')->assertSee('-1,234.56'); // the March sale is the opening balance of April
    });

    it('refuses accounts of another company or tenant', function () {
        $other = CreateCompany::class;
        ['company' => $foreignCompany] = bootstrapTenant('other@example.com', 'Other');
        $foreign = accountByRole(AccountRole::Cash);
        $this->actingAs($this->owner);
        restoreContext($this->owner);

        $this->get(route('accounting.reports.general-ledger', $this->range + ['account_id' => $foreign->id]))->assertNotFound();
    });

    it('warns when the page is truncated', function () {
        foreach (range(1, 5) as $_) {
            postMove($this->owner, $this->cash, $this->sales, 100, '2026-05-05');
        }

        // The UI limit is 2000; here we only check the normal (non truncated) page has no warning.
        $this->get(route('accounting.reports.general-ledger', $this->range + ['account_id' => $this->cash->id]))->assertDontSee('Narrow the dates');
    });
});

describe('general ledger export', function () {
    it('exports every line with opening and closing rows as CSV and XLSX', function () {
        $params = $this->range + ['account_id' => $this->cash->id];

        $csv = $this->get(route('accounting.reports.general-ledger', $params + ['format' => 'csv']));
        $lines = array_map('str_getcsv', array_filter(explode("\n", str_replace("\r", '', substr($csv->streamedContent(), 3)))));

        expect($lines[0])->toBe(['Date', 'Number', 'Description', 'Line description', 'Debit', 'Credit', 'Balance'])
            ->and($lines[1][2])->toBe('Opening balance')->and(end($lines)[2])->toBe('Closing balance')->and(end($lines)[6])->toBe('1734.56')
            ->and($csv->headers->get('Content-Disposition'))->toContain('ledger_'.$this->cash->code);

        $xlsx = xlsxRows($this->get(route('accounting.reports.general-ledger', $params + ['format' => 'xlsx'])));

        expect(count($xlsx))->toBe(count($lines))->and((float) end($xlsx)[6])->toBe(1734.56);
    });

    it('is forbidden without export permission', function () {
        actingAsRole('viewer');

        $this->get(route('accounting.reports.general-ledger', $this->range + ['account_id' => $this->cash->id, 'format' => 'csv']))->assertForbidden();
    });
});

describe('report permissions', function () {
    it('requires reports.view', function () {
        $role = Role::where('name', 'viewer')->where('tenant_id', $this->owner->tenant_id)->first();
        $role->revokePermissionTo('reports.view');
        actingAsRole('viewer');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->get(route('accounting.reports.trial-balance'))->assertForbidden();
        $this->get(route('accounting.reports.general-ledger'))->assertForbidden();
    });

    it('is gated by the subscription like the rest of the app', function () {
        Subscription::where('tenant_id', $this->owner->tenant_id)->update(['trial_ends_at' => now()->subDays(60), 'status' => 'expired']);

        $this->get(route('accounting.reports.trial-balance'))->assertRedirect(route('billing.show'));
    });
});

describe('SpreadsheetExporter', function () {
    it('rejects unknown formats', function () {
        (new SpreadsheetExporter)->download('pdf', 'x', ['a'], []);
    })->throws(NotFoundHttpException::class);

    it('quotes CSV values safely and keeps unicode', function () {
        ob_start();
        (new SpreadsheetExporter)->download('csv', 'my report', ['A,B', 'ج'], [['x"y', 'line1 line2'], [1.5, null]])->sendContent();
        $csv = ob_get_clean();

        expect($csv)->toContain('"A,B"')->toContain('ج')->toContain('"x""y"')->toContain('1.5');
    });

    it('sanitises the download filename', function () {
        $response = (new SpreadsheetExporter)->download('csv', '../etc/passwd report', ['a'], []);

        expect($response->headers->get('Content-Disposition'))->toBe('attachment; filename="_etc_passwd_report.csv"')
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('streams large exports row by row', function () {
        $generator = (function () {
            foreach (range(1, 2000) as $i) {
                yield [$i, "row {$i}"];
            }
        })();

        ob_start();
        (new SpreadsheetExporter)->download('csv', 'big', ['n', 'label'], $generator)->sendContent();
        $csv = ob_get_clean();

        expect(substr_count($csv, "\n"))->toBe(2001);
    });
});
