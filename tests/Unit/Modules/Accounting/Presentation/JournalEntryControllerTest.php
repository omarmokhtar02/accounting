<?php

use Illuminate\Support\Facades\Auth;
use Modules\Accounting\Domain\Enums\AccountRole;
use Modules\Accounting\Domain\Enums\JournalStatus;
use Modules\Accounting\Domain\Models\JournalEntry;
use Modules\Accounting\Presentation\Controllers\JournalEntryController;
use Modules\Accounting\Presentation\Requests\StoreJournalEntryRequest;

covers(JournalEntryController::class, StoreJournalEntryRequest::class);

beforeEach(function () {
    ['user' => $this->owner, 'company' => $this->company] = bootstrapTenant();
    openFiscalYear2026();
    $this->cash = accountByRole(AccountRole::Cash);
    $this->sales = accountByRole(AccountRole::SalesRevenue);
});

function journalForm(object $test, string $debit, string $credit, array $extra = []): array
{
    return $extra + [
        'entry_date' => '2026-03-15', 'description' => 'Form sale', 'idempotency_key' => 'form-'.uniqid(),
        'lines' => [
            ['account_id' => $test->cash->id, 'debit' => $debit, 'credit' => ''],
            ['account_id' => $test->sales->id, 'debit' => '', 'credit' => $credit],
        ],
    ];
}

it('lists entries with totals formatted in the company currency', function () {
    postedJournal($this->owner, 123456);

    $this->get(route('accounting.journal-entries.index'))->assertOk()->assertSee('JV-2026-000001')->assertSee('1,234.56');
});

it('shows the entry form with postable accounts only', function () {
    $this->get(route('accounting.journal-entries.create'))->assertOk()->assertSee('1213')->assertDontSee('>12 — ', false);
});

it('creates a draft from the form', function () {
    $response = $this->post(route('accounting.journal-entries.store'), journalForm($this, '150.75', '150.75'));

    restoreContext($this->owner);
    $entry = JournalEntry::first();

    $response->assertRedirect(route('accounting.journal-entries.show', $entry));
    expect($entry->status)->toBe(JournalStatus::Draft)->and($entry->lines->sum('debit'))->toBe(15075);
});

it('is idempotent for a repeated form submission', function () {
    $form = journalForm($this, '10', '10', ['idempotency_key' => 'once']);

    $this->post(route('accounting.journal-entries.store'), $form);
    $this->post(route('accounting.journal-entries.store'), $form);

    restoreContext($this->owner);
    expect(JournalEntry::count())->toBe(1);
});

it('shows an unbalanced entry as an error and keeps the input', function () {
    $this->post(route('accounting.journal-entries.store'), journalForm($this, '100', '90'))
        ->assertSessionHasErrors('lines')->assertSessionHasInput('description', 'Form sale');

    restoreContext($this->owner);
    expect(JournalEntry::count())->toBe(0);
});

it('validates the form fields and the amount format', function (array $input, string $field) {
    $this->post(route('accounting.journal-entries.store'), $input + journalForm($this, '10', '10'))->assertSessionHasErrors($field);
})->with([
    'no description' => [['description' => ''], 'description'],
    'bad date' => [['entry_date' => 'x'], 'entry_date'],
    'one line only' => [['lines' => [['account_id' => 1, 'debit' => '1']]], 'lines'],
    'three decimals in EGP' => [['lines' => [['account_id' => 1, 'debit' => '1.234'], ['account_id' => 1, 'credit' => '1.234']]], 'lines.0.debit'],
    'negative amount' => [['lines' => [['account_id' => 1, 'debit' => '-5'], ['account_id' => 1, 'credit' => '5']]], 'lines.0.debit'],
]);

it('accepts three decimals for a Kuwaiti dinar company and rejects four', function () {
    bootstrapTenant('kw@example.com', 'Kuwait Co', 'KW');
    openFiscalYear2026();
    $cash = accountByRole(AccountRole::Cash);
    $sales = accountByRole(AccountRole::SalesRevenue);
    $form = fn (string $amount) => [
        'entry_date' => '2026-03-15', 'description' => 'KWD',
        'lines' => [['account_id' => $cash->id, 'debit' => $amount], ['account_id' => $sales->id, 'credit' => $amount]],
    ];

    $this->post(route('accounting.journal-entries.store'), $form('12.3456'))->assertSessionHasErrors('lines.0.debit');
    $this->post(route('accounting.journal-entries.store'), $form('12.345'))->assertSessionHasNoErrors()->assertRedirect();

    $user = auth()->user();
    restoreContext($user);
    expect(JournalEntry::first()->lines->sum('debit'))->toBe(12345)->and(JournalEntry::first()->currency)->toBe('KWD');
});

it('shows an entry with lines, status and audit trail', function () {
    $entry = postedJournal($this->owner, 5000);

    $this->get(route('accounting.journal-entries.show', $entry))->assertOk()
        ->assertSee('JV-2026-000001')->assertSee('50.00')->assertSee('Posted')->assertSee('posted');
});

it('posts a draft as another user and refuses self posting without the permission', function () {
    $entry = draftJournal();
    $this->post(route('accounting.journal-entries.post', $entry))->assertSessionHas('status');
    restoreContext($this->owner);
    expect($entry->fresh()->status)->toBe(JournalStatus::Posted);

    $draft = draftJournal();
    actingAsRole('accountant');
    $this->post(route('accounting.journal-entries.post', $draft))->assertForbidden();
});

it('refuses self posting when the poster lacks journals.post_own', function () {
    $senior = actingAsRole('senior_accountant');
    restoreContext($senior);
    Auth::login($senior);
    $draft = draftJournal();

    $this->post(route('accounting.journal-entries.post', $draft))->assertSessionHasErrors('journal');
});

it('reverses a posted entry', function () {
    $entry = postedJournal($this->owner);

    $this->post(route('accounting.journal-entries.reverse', $entry), ['reversal_date' => '2026-03-20', 'reason' => 'oops'])
        ->assertRedirect()->assertSessionHas('status');

    restoreContext($this->owner);
    expect($entry->fresh()->status)->toBe(JournalStatus::Reversed)->and(JournalEntry::count())->toBe(2);
});

it('validates and reports reversal errors', function () {
    $entry = postedJournal($this->owner);

    $this->post(route('accounting.journal-entries.reverse', $entry), [])->assertSessionHasErrors('reversal_date');
    $this->post(route('accounting.journal-entries.reverse', $entry), ['reversal_date' => '2030-01-01'])->assertSessionHasErrors('journal');
});

it('returns 404 for another tenant\'s entry', function () {
    $entry = postedJournal($this->owner);
    bootstrapTenant('other@example.com', 'Other');

    $this->get(route('accounting.journal-entries.show', $entry->id))->assertNotFound();
});

it('enforces journal permissions', function (string $role, string $route, int $status) {
    $entry = draftJournal();
    actingAsRole($role);
    $needsEntry = in_array($route, ['accounting.journal-entries.show'], true);

    $response = str_contains($route, 'store') || str_contains($route, 'post') || str_contains($route, 'reverse')
        ? $this->post(route($route, $needsEntry || str_contains($route, 'post') || str_contains($route, 'reverse') ? [$entry] : []))
        : $this->get(route($route, $needsEntry ? [$entry] : []));

    expect($response->getStatusCode())->toBe($status);
})->with([
    'auditor views list' => ['auditor', 'accounting.journal-entries.index', 200],
    'auditor views entry' => ['auditor', 'accounting.journal-entries.show', 200],
    'auditor cannot open form' => ['auditor', 'accounting.journal-entries.create', 403],
    'accountant opens form' => ['accountant', 'accounting.journal-entries.create', 200],
    'accountant cannot post' => ['accountant', 'accounting.journal-entries.post', 403],
    'accountant cannot reverse' => ['accountant', 'accounting.journal-entries.reverse', 403],
]);

describe('foreign currency lines', function () {
    it('shows a currency picker on the form', function () {
        $this->get(route('accounting.journal-entries.create'))->assertOk()->assertSee('lines[0][currency]', false)->assertSee('<option value="USD"', false);
    });

    it('saves a USD line converted at the line rate and shows the transacted amount', function () {
        $form = journalForm($this, '100', '5000', ['lines' => [
            ['account_id' => $this->cash->id, 'debit' => '100', 'credit' => '', 'currency' => 'USD', 'rate' => '50'],
            ['account_id' => $this->sales->id, 'debit' => '', 'credit' => '5000', 'currency' => 'EGP'],
        ]]);

        $response = $this->post(route('accounting.journal-entries.store'), $form);
        restoreContext($this->owner);
        $entry = JournalEntry::latest('id')->first();
        $response->assertRedirect(route('accounting.journal-entries.show', $entry));

        expect($entry->lines->firstWhere('txn_currency', 'USD')->debit)->toBe(500000);

        $this->get(route('accounting.journal-entries.show', $entry))->assertSee('100.00')->assertSee('× 50');
    });

    it('takes three decimals on a KWD line even in an EGP company', function () {
        $form = journalForm($this, '1', '1', ['lines' => [
            ['account_id' => $this->cash->id, 'debit' => '1.234', 'credit' => '', 'currency' => 'KWD', 'rate' => '160'],
            ['account_id' => $this->sales->id, 'debit' => '', 'credit' => '197.44'],
        ]]);

        $this->post(route('accounting.journal-entries.store'), $form)->assertSessionHasNoErrors();
    });

    it('rejects a missing rate with a readable message and a malformed rate or currency', function () {
        $line = ['account_id' => $this->cash->id, 'debit' => '10', 'credit' => '', 'currency' => 'USD'];
        $other = ['account_id' => $this->sales->id, 'debit' => '', 'credit' => '500'];

        $this->post(route('accounting.journal-entries.store'), journalForm($this, '1', '1', ['lines' => [$line, $other]]))->assertSessionHasErrors('lines');
        $this->post(route('accounting.journal-entries.store'), journalForm($this, '1', '1', ['lines' => [$line + ['rate' => 'x'], $other]]))->assertSessionHasErrors('lines.0.rate');
        $this->post(route('accounting.journal-entries.store'), journalForm($this, '1', '1', ['lines' => [['currency' => 'ZZZ'] + $line, $other]]))->assertSessionHasErrors('lines.0.currency');
    });

    it('survives a garbage lines payload', function () {
        $this->post(route('accounting.journal-entries.store'), ['entry_date' => '2026-03-15', 'description' => 'x', 'lines' => ['oops', 'again']])->assertSessionHasErrors();
    });
});
