<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Domain\Models\Account;
use Modules\Accounting\Domain\Models\ExchangeRateEntry;
use Modules\Accounting\Presentation\Controllers\ImportController;

covers(ImportController::class);

function csvUpload(string $content, string $name = 'data.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

/** Pulls the confirmation token out of the preview page. */
function tokenFrom($response): string
{
    preg_match('/name="token" value="([^"]+)"/', $response->getContent(), $m);

    return $m[1];
}

beforeEach(function () {
    Storage::fake('local');
    $this->owner = bootstrapTenant()['user'];
});

it('shows the upload form and the templates', function () {
    $this->get(route('accounting.imports.index'))->assertOk()
        ->assertSee('Chart of accounts')->assertSee('Exchange rates')->assertSee('code, name, type');
});

it('renders in Arabic', function () {
    $this->withSession(['locale' => 'ar'])->get(route('accounting.imports.index'))->assertOk()->assertSee('دليل الحسابات');
});

it('downloads templates as CSV and XLSX and 404s on unknown ones', function () {
    $csv = $this->get(route('accounting.imports.template', ['rates', 'csv']));

    $csv->assertOk();
    expect($csv->streamedContent())->toContain('currency,date,rate')->toContain('USD');

    $this->get(route('accounting.imports.template', ['rates', 'xlsx']))->assertOk();
    $this->get(route('accounting.imports.template', ['nope', 'csv']))->assertNotFound();
    $this->get(route('accounting.imports.template', ['rates', 'pdf']))->assertNotFound();
});

it('previews a valid file, then imports it on confirmation', function () {
    $preview = $this->post(route('accounting.imports.preview'), ['type' => 'rates', 'file' => csvUpload("currency,date,rate\nUSD,2026-01-01,48.5\n")]);

    $preview->assertOk()->assertSee('The file is valid');
    expect(ExchangeRateEntry::count())->toBe(0);

    restoreContext($this->owner);

    $done = $this->post(route('accounting.imports.commit'), ['type' => 'rates', 'token' => tokenFrom($preview)]);

    $done->assertOk()->assertSee('Import finished');
    restoreContext($this->owner);
    expect(ExchangeRateEntry::count())->toBe(1);
});

it('lists the problems and offers no import button', function () {
    $this->post(route('accounting.imports.preview'), ['type' => 'rates', 'file' => csvUpload("currency,date,rate\nZZZ,2026-01-01,1\nUSD,2026-01-01,abc\n")])
        ->assertOk()->assertSee('Unknown currency')->assertSee('not a valid rate')->assertDontSee('name="token"', false);
});

it('imports a chart of accounts end to end', function () {
    $preview = $this->post(route('accounting.imports.preview'), ['type' => 'accounts', 'file' => csvUpload("code,name,type\n9801,Imported,expense\n")]);
    restoreContext($this->owner);
    $this->post(route('accounting.imports.commit'), ['type' => 'accounts', 'token' => tokenFrom($preview)])->assertOk();

    restoreContext($this->owner);
    expect(Account::where('code', '9801')->exists())->toBeTrue();
});

it('rejects files with missing columns, wrong types or the wrong import kind', function () {
    $this->post(route('accounting.imports.preview'), ['type' => 'rates', 'file' => csvUpload("a,b\n1,2\n")])->assertSessionHasErrors('file');
    $this->post(route('accounting.imports.preview'), ['type' => 'rates', 'file' => UploadedFile::fake()->create('x.pdf', 10)])->assertSessionHasErrors('file');
    $this->post(route('accounting.imports.preview'), ['type' => 'nope', 'file' => csvUpload("a\n1\n")])->assertNotFound();
    $this->post(route('accounting.imports.preview'), ['type' => 'rates'])->assertSessionHasErrors('file');
});

it('does not keep a rejected file', function () {
    $this->post(route('accounting.imports.preview'), ['type' => 'rates', 'file' => csvUpload("a,b\n1,2\n")]);

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses a commit with a forged, missing or malformed token', function () {
    $this->post(route('accounting.imports.commit'), ['type' => 'rates', 'token' => '../../.env'])->assertSessionHasErrors('token');
    $this->post(route('accounting.imports.commit'), ['type' => 'rates', 'token' => str_repeat('a', 40).'.csv'])->assertNotFound();
});

it('does not let another company use an uploaded file', function () {
    $preview = $this->post(route('accounting.imports.preview'), ['type' => 'rates', 'file' => csvUpload("currency,date,rate\nUSD,2026-01-01,48.5\n")]);
    $token = tokenFrom($preview);

    bootstrapTenant('other@example.com', 'Other');

    $this->post(route('accounting.imports.commit'), ['type' => 'rates', 'token' => $token])->assertNotFound();
});

it('requires the import permission', function () {
    restoreContext($this->owner);
    actingAsRole('viewer');

    $this->get(route('accounting.imports.index'))->assertForbidden();
    $this->post(route('accounting.imports.preview'), ['type' => 'rates'])->assertForbidden();
});
