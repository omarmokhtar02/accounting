<?php

use Carbon\CarbonImmutable;
use Modules\Accounting\Application\AccountRoleResolver;
use Modules\Accounting\Application\Commands\CreateJournalEntry;
use Modules\Accounting\Application\Commands\PostJournalEntry;
use Modules\Accounting\Application\Commands\ReverseJournalEntry;
use Modules\Accounting\Application\DTOs\CreateJournalEntryData;
use Modules\Accounting\Application\DTOs\JournalLineData;
use Modules\Accounting\Application\Reporting\GeneralLedgerReport;
use Modules\Accounting\Application\Reporting\GeneralLedgerResult;
use Modules\Accounting\Application\Reporting\LedgerLine;
use Modules\Accounting\Application\Reporting\TrialBalanceReport;
use Modules\Accounting\Application\Reporting\TrialBalanceResult;
use Modules\Accounting\Application\Reporting\TrialBalanceRow;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Localization\Application\CreateCompany;
use Modules\Tenancy\Infrastructure\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

covers(TrialBalanceReport::class, TrialBalanceResult::class, TrialBalanceRow::class, GeneralLedgerReport::class, GeneralLedgerResult::class, LedgerLine::class);

/** Posts Dr $debit / Cr $credit (account ids) for $amount minor units on $date. */
function post(object $user, Account $debit, Account $credit, int $amount, string $date, bool $draftOnly = false): JournalEntry
{
    $entry = app(CreateJournalEntry::class)->handle(new CreateJournalEntryData(
        CarbonImmutable::parse($date), "Move {$amount}",
        [new JournalLineData($debit->id, $amount, 0), new JournalLineData($credit->id, 0, $amount)],
    ));

    return $draftOnly ? $entry : app(PostJournalEntry::class)->handle($entry, $user, allowSelfPosting: true);
}

beforeEach(function () {
    ['user' => $this->user, 'company' => $this->company] = bootstrapTenant();
    openFiscalYear2026();
    $this->cash = accountByRole(AccountRole::Cash);
    $this->sales = accountByRole(AccountRole::SalesRevenue);
    $this->bank = accountByRole(AccountRole::Bank);
    $this->rent = Account::where('company_id', $this->company->id)->where('code', '6103')->first();
    $this->tb = app(TrialBalanceReport::class);
    $this->gl = app(GeneralLedgerReport::class);
});

describe('TrialBalanceRow', function () {
    it('computes closing balances and splits them into debit and credit columns', function () {
        $debit = new TrialBalanceRow(1, '1000', 'Cash', 'نقدية', 'asset', 500, 300, 100);
        $credit = new TrialBalanceRow(2, '4000', 'Sales', 'مبيعات', 'revenue', -500, 100, 300);

        expect($debit->closingNet())->toBe(700)->and($debit->closingDebit())->toBe(700)->and($debit->closingCredit())->toBe(0)
            ->and($debit->openingDebit())->toBe(500)->and($debit->openingCredit())->toBe(0)
            ->and($credit->closingNet())->toBe(-700)->and($credit->closingCredit())->toBe(700)->and($credit->closingDebit())->toBe(0)
            ->and($credit->openingCredit())->toBe(500);
    });

    it('shows the Arabic name in Arabic and falls back to English', function () {
        $row = new TrialBalanceRow(1, '1', 'Cash', 'نقدية', 'asset', 0, 0, 0);
        $noAr = new TrialBalanceRow(1, '1', 'Cash', '', 'asset', 0, 0, 0);

        app()->setLocale('ar');
        expect($row->localizedName())->toBe('نقدية')->and($noAr->localizedName())->toBe('Cash');

        app()->setLocale('en');
        expect($row->localizedName())->toBe('Cash');
    });
});

describe('TrialBalanceResult', function () {
    it('totals every column and knows whether it balances', function () {
        $balanced = new TrialBalanceResult('EGP', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'), [
            new TrialBalanceRow(1, '1', 'A', 'أ', 'asset', 0, 1000, 0),
            new TrialBalanceRow(2, '2', 'B', 'ب', 'revenue', 0, 0, 1000),
        ]);
        $broken = new TrialBalanceResult('EGP', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'), [
            new TrialBalanceRow(1, '1', 'A', 'أ', 'asset', 0, 1000, 0),
            new TrialBalanceRow(2, '2', 'B', 'ب', 'revenue', 0, 0, 900),
        ]);

        expect($balanced->totalDebit())->toBe(1000)->and($balanced->totalCredit())->toBe(1000)->and($balanced->totalClosingNet())->toBe(0)
            ->and($balanced->totalClosingDebit())->toBe(1000)->and($balanced->totalClosingCredit())->toBe(1000)->and($balanced->totalOpeningNet())->toBe(0)
            ->and($balanced->isBalanced())->toBeTrue()->and($broken->isBalanced())->toBeFalse();
    });

    it('is empty and balanced with no rows', function () {
        expect((new TrialBalanceResult('EGP', CarbonImmutable::now(), CarbonImmutable::now(), []))->isBalanced())->toBeTrue();
    });
});

describe('TrialBalanceReport', function () {
    it('reports opening, period movement and closing balances', function () {
        post($this->user, $this->cash, $this->sales, 100000, '2026-01-10');   // before the period
        post($this->user, $this->cash, $this->sales, 40000, '2026-03-05');    // in period
        post($this->user, $this->rent, $this->cash, 15000, '2026-03-20');     // in period
        post($this->user, $this->cash, $this->sales, 99999, '2026-05-01');    // after the period

        $report = $this->tb->build(CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'));
        $rows = collect($report->rows)->keyBy('code');

        $cash = $rows[$this->cash->code];
        $sales = $rows[$this->sales->code];
        $rent = $rows['6103'];

        expect($cash->openingNet)->toBe(100000)->and($cash->debit)->toBe(40000)->and($cash->credit)->toBe(15000)->and($cash->closingNet())->toBe(125000)
            ->and($sales->openingNet)->toBe(-100000)->and($sales->credit)->toBe(40000)->and($sales->closingNet())->toBe(-140000)
            ->and($rent->openingNet)->toBe(0)->and($rent->debit)->toBe(15000)->and($rent->closingNet())->toBe(15000)
            ->and($report->isBalanced())->toBeTrue()->and($report->currency)->toBe('EGP');
    });

    it('includes both boundary dates of the period', function () {
        post($this->user, $this->cash, $this->sales, 1000, '2026-03-01');
        post($this->user, $this->cash, $this->sales, 2000, '2026-03-31');
        post($this->user, $this->cash, $this->sales, 4000, '2026-02-28');
        post($this->user, $this->cash, $this->sales, 8000, '2026-04-01');

        $cash = collect($this->tb->build(CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'))->rows)->firstWhere('code', $this->cash->code);

        expect($cash->openingNet)->toBe(4000)->and($cash->debit)->toBe(3000);
    });

    it('ignores drafts: only posted entries are in the books', function () {
        post($this->user, $this->cash, $this->sales, 5000, '2026-03-10');
        post($this->user, $this->cash, $this->sales, 7777, '2026-03-11', draftOnly: true);

        $report = $this->tb->build(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));

        expect($report->totalDebit())->toBe(5000);
    });

    it('nets a reversed entry to zero because the reversal is posted too', function () {
        $entry = post($this->user, $this->cash, $this->sales, 5000, '2026-03-10');
        app(ReverseJournalEntry::class)->handle($entry, $this->user, CarbonImmutable::parse('2026-03-12'));

        $report = $this->tb->build(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
        $cash = collect($report->rows)->firstWhere('code', $this->cash->code);

        expect($cash->debit)->toBe(5000)->and($cash->credit)->toBe(5000)->and($cash->closingNet())->toBe(0)->and($report->isBalanced())->toBeTrue();
    });

    it('hides accounts with no activity unless asked to show them, ordered by code', function () {
        post($this->user, $this->cash, $this->sales, 5000, '2026-03-10');

        $hidden = $this->tb->build(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'), true);
        $all = $this->tb->build(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'), false);

        expect(collect($hidden->rows)->pluck('code')->all())->toBe(collect($hidden->rows)->pluck('code')->sort()->values()->all())
            ->and($hidden->rows)->toHaveCount(2)
            ->and(count($all->rows))->toBe(Account::where('company_id', $this->company->id)->where('is_postable', true)->count())
            ->and($all->isBalanced())->toBeTrue();
    });

    it('is empty and balanced for a company with no postings', function () {
        $report = $this->tb->build(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));

        expect($report->rows)->toBe([])->and($report->isBalanced())->toBeTrue();
    });

    it('only ever reports the active company', function () {
        post($this->user, $this->cash, $this->sales, 5000, '2026-03-10');
        $second = app(CreateCompany::class)->handle('Second', null, 'SA');
        openFiscalYear2026();
        $cashB = accountByRole(AccountRole::Cash);
        post($this->user, $cashB, accountByRole(AccountRole::SalesRevenue), 12345, '2026-03-10');

        $b = $this->tb->build(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));

        expect($b->totalDebit())->toBe(12345)->and($b->currency)->toBe('SAR');

        app(TenantContext::class)->setCompany($this->company);

        expect($this->tb->build(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'))->totalDebit())->toBe(5000);
    });

    it('never shows another tenant\'s postings', function () {
        post($this->user, $this->cash, $this->sales, 5000, '2026-03-10');

        ['user' => $other] = bootstrapTenant('other@example.com', 'Other');
        openFiscalYear2026();

        expect($this->tb->build(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'))->rows)->toBe([]);
    });

    it('always balances, whatever mix of entries is posted (invariant over random ledgers)', function () {
        mt_srand(20260101);
        $accounts = Account::where('company_id', $this->company->id)->where('is_postable', true)->whereNotIn('id', array_values(app(AccountRoleResolver::class)->controlAccountIds()))->get();

        foreach (range(1, 60) as $i) {
            [$a, $b] = [$accounts->random(), $accounts->random()];

            if ($a->id === $b->id) {
                continue;
            }

            post($this->user, $a, $b, mt_rand(1, 9_999_999), CarbonImmutable::parse('2026-01-01')->addDays(mt_rand(0, 360))->toDateString());
        }

        $year = $this->tb->build(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
        $half = $this->tb->build(CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-12-31'));

        expect($year->isBalanced())->toBeTrue()->and($half->isBalanced())->toBeTrue()
            ->and($year->totalDebit())->toBe($year->totalCredit())->and($year->totalClosingNet())->toBe(0);
    });
});

describe('GeneralLedgerReport', function () {
    it('shows the opening balance, each line with a running balance and the closing balance', function () {
        post($this->user, $this->cash, $this->sales, 100000, '2026-01-10');
        post($this->user, $this->cash, $this->sales, 40000, '2026-03-05');
        post($this->user, $this->rent, $this->cash, 15000, '2026-03-20');
        post($this->user, $this->cash, $this->sales, 1, '2026-05-01');

        $ledger = $this->gl->build($this->cash, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'));

        expect($ledger->openingBalance)->toBe(100000)->and($ledger->lines)->toHaveCount(2)
            ->and($ledger->lines[0]->debit)->toBe(40000)->and($ledger->lines[0]->runningBalance)->toBe(140000)
            ->and($ledger->lines[1]->credit)->toBe(15000)->and($ledger->lines[1]->runningBalance)->toBe(125000)
            ->and($ledger->totalDebit())->toBe(40000)->and($ledger->totalCredit())->toBe(15000)->and($ledger->closingBalance())->toBe(125000)
            ->and($ledger->truncated)->toBeFalse()->and($ledger->currency)->toBe('EGP')->and($ledger->account->id)->toBe($this->cash->id);
    });

    it('orders lines by date then entry, and carries the journal number for drill-down', function () {
        $late = post($this->user, $this->cash, $this->sales, 3000, '2026-03-20');
        $early = post($this->user, $this->cash, $this->sales, 1000, '2026-03-02');
        $same = post($this->user, $this->cash, $this->sales, 2000, '2026-03-02');

        $lines = $this->gl->build($this->cash, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'))->lines;

        expect(array_map(fn ($l) => $l->journalEntryId, $lines))->toBe([$early->id, $same->id, $late->id])
            ->and($lines[0]->number)->toBe($early->fresh()->number)->and($lines[0]->date)->toBe('2026-03-02')
            ->and(array_map(fn ($l) => $l->runningBalance, $lines))->toBe([1000, 3000, 6000]);
    });

    it('ties out to the trial balance for every account', function () {
        post($this->user, $this->cash, $this->sales, 100000, '2026-01-10');
        post($this->user, $this->rent, $this->cash, 15000, '2026-02-20');
        post($this->user, $this->bank, $this->cash, 30000, '2026-03-01');
        $from = CarbonImmutable::parse('2026-02-01');
        $to = CarbonImmutable::parse('2026-12-31');

        foreach ($this->tb->build($from, $to)->rows as $row) {
            $ledger = $this->gl->build(Account::find($row->accountId), $from, $to);

            expect($ledger->closingBalance())->toBe($row->closingNet(), "GL and TB disagree for account {$row->code}")
                ->and($ledger->openingBalance)->toBe($row->openingNet)
                ->and($ledger->totalDebit())->toBe($row->debit)->and($ledger->totalCredit())->toBe($row->credit);
        }
    });

    it('excludes drafts and shows a reversal as its own line', function () {
        $entry = post($this->user, $this->cash, $this->sales, 5000, '2026-03-10');
        post($this->user, $this->cash, $this->sales, 999, '2026-03-11', draftOnly: true);
        app(ReverseJournalEntry::class)->handle($entry, $this->user, CarbonImmutable::parse('2026-03-12'));

        $ledger = $this->gl->build($this->cash, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'));

        expect($ledger->lines)->toHaveCount(2)->and($ledger->closingBalance())->toBe(0);
    });

    it('truncates at the UI limit but exports everything when no limit is given', function () {
        foreach (range(1, 5) as $i) {
            post($this->user, $this->cash, $this->sales, $i * 100, '2026-03-1'.$i);
        }
        $from = CarbonImmutable::parse('2026-03-01');
        $to = CarbonImmutable::parse('2026-03-31');

        $limited = $this->gl->build($this->cash, $from, $to, 3);
        $unlimited = $this->gl->build($this->cash, $from, $to, null);

        expect($limited->lines)->toHaveCount(3)->and($limited->truncated)->toBeTrue()
            ->and($unlimited->lines)->toHaveCount(5)->and($unlimited->truncated)->toBeFalse()
            ->and($this->gl->build($this->cash, $from, $to, 5)->truncated)->toBeFalse();
    });

    it('is empty for an account without activity', function () {
        $ledger = $this->gl->build($this->rent, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));

        expect($ledger->lines)->toBe([])->and($ledger->openingBalance)->toBe(0)->and($ledger->closingBalance())->toBe(0);
    });

    it('refuses an account of another company', function () {
        $second = app(CreateCompany::class)->handle('Second', null, 'SA');
        $foreign = accountByRole(AccountRole::Cash);
        app(TenantContext::class)->setCompany($this->company);

        $this->gl->build($foreign, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
    })->throws(NotFoundHttpException::class);

    it('describes a ledger line and result', function () {
        $line = new LedgerLine(7, '2026-03-02', 'JV-1', 'Entry', null, 100, 0, 100);
        $result = new GeneralLedgerResult($this->cash, 'EGP', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'), 50, [$line], true);

        expect($line->journalEntryId)->toBe(7)->and($line->lineDescription)->toBeNull()
            ->and($result->closingBalance())->toBe(150)->and($result->truncated)->toBeTrue();
    });
});
