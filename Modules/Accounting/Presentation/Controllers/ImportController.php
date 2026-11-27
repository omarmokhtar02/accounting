<?php

namespace Modules\Accounting\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Accounting\Application\Import\ImportCatalog;
use Modules\Accounting\Application\Import\Importer;
use Modules\Accounting\Domain\Exceptions\DomainException;
use Modules\Shared\Domain\Exceptions\InvalidImportFile;
use Modules\Shared\Infrastructure\Export\SpreadsheetExporter;
use Modules\Shared\Infrastructure\Import\SpreadsheetReader;
use Modules\Tenancy\Infrastructure\TenantContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload, preview, then commit. The preview validates the whole file and writes nothing; the uploaded
 * file waits in tenant-private storage under a random token until the user confirms.
 */
class ImportController extends Controller
{
    public function index(ImportCatalog $catalog): View
    {
        return view('accounting::imports.index', ['importers' => $catalog->all(), 'report' => null]);
    }

    public function template(ImportCatalog $catalog, string $type, string $format, SpreadsheetExporter $exporter): StreamedResponse
    {
        $importer = $this->importer($catalog, $type);

        return $exporter->download($format, 'template-'.$importer->key(), $importer->templateColumns(), $importer->sampleRows());
    }

    public function preview(Request $request, ImportCatalog $catalog, SpreadsheetReader $reader, TenantContext $context): View|RedirectResponse
    {
        $request->validate([
            'type' => ['required', 'string'],
            'file' => ['required', 'file', 'max:5120', 'extensions:csv,xlsx'],
        ]);

        $importer = $this->importer($catalog, $request->string('type')->toString());
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $token = Str::random(40);
        $stored = $file->storeAs($this->directory($context), $token.'.'.$extension, 'local');
        $path = Storage::disk('local')->path($stored);

        try {
            $rows = $reader->read($path, $extension, $importer->requiredColumns());
            $report = $importer->run($rows, hash_file('sha256', $path), false);
        } catch (InvalidImportFile $e) {
            Storage::disk('local')->delete($stored);

            return back()->withErrors(['file' => $e->userMessage()]);
        }

        return view('accounting::imports.index', [
            'importers' => $catalog->all(), 'report' => $report, 'type' => $importer->key(), 'token' => $token.'.'.$extension,
        ]);
    }

    public function commit(Request $request, ImportCatalog $catalog, SpreadsheetReader $reader, TenantContext $context): View|RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'token' => ['required', 'regex:/^[A-Za-z0-9]{40}\.(csv|xlsx)$/'],
        ]);

        $importer = $this->importer($catalog, $data['type']);
        $stored = $this->directory($context).'/'.$data['token'];

        abort_unless(Storage::disk('local')->exists($stored), 404);

        $path = Storage::disk('local')->path($stored);

        try {
            $rows = $reader->read($path, pathinfo($stored, PATHINFO_EXTENSION), $importer->requiredColumns());
            $report = $importer->run($rows, hash_file('sha256', $path), true);
        } catch (InvalidImportFile|DomainException $e) {
            return redirect()->route('accounting.imports.index')->withErrors(['file' => $e->userMessage()]);
        }

        Storage::disk('local')->delete($stored);

        return view('accounting::imports.index', ['importers' => $catalog->all(), 'report' => $report, 'type' => $importer->key(), 'token' => null]);
    }

    private function importer(ImportCatalog $catalog, string $type): Importer
    {
        return $catalog->find($type) ?? abort(404);
    }

    private function directory(TenantContext $context): string
    {
        return 'imports/'.$context->tenantId().'/'.$context->companyId();
    }
}
