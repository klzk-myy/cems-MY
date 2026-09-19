<?php

namespace App\Http\Controllers;

use App\Enums\TransactionImportStatus;
use App\Exceptions\Domain\FileOperationException;
use App\Http\Requests\BatchUploadRequest;
use App\Jobs\ProcessTransactionImportJob;
use App\Models\TransactionImport;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Compliance\ComplianceService;
use App\Services\Reporting\CsvReportWriter;
use App\Services\System\DocumentStorageService;
use App\Services\System\MathService;
use App\Services\Transaction\TransactionImportService;
use App\Services\Transaction\TransactionMonitoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransactionBatchController extends Controller
{
    public function __construct(
        protected MathService $mathService,
        protected ComplianceService $complianceService,
        protected CurrencyPositionService $positionService,
        protected AccountingService $accountingService,
        protected TransactionMonitoringService $monitoringService,
        protected DocumentStorageService $documentStorageService,
        protected TransactionImportService $importService,
        protected CsvReportWriter $csvReportWriter,
        protected LoggerInterface $logger
    ) {}

    /**
     * Show batch upload form
     */
    public function showBatchUpload(): View
    {
        $recentImports = TransactionImport::where('imported_by', auth()->id())
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return view('transactions.batch-upload', compact('recentImports'));
    }

    /**
     * Process batch upload
     */
    public function processBatchUpload(BatchUploadRequest $request): RedirectResponse
    {
        $file = $request->file('csv_file');

        // Store file
        $path = $file->store('imports');

        if ($path === false) {
            return back()->with('error', 'Could not store the uploaded file.')->withInput();
        }

        // Get the full file path - use actual file path for testing, Storage::path otherwise
        $fullPath = $this->documentStorageService->exists($path) ? $this->documentStorageService->path($path) : $file->getRealPath();

        // If file still doesn't exist at Storage path, fall back to temp path
        if (! file_exists($fullPath)) {
            $fullPath = $file->getRealPath();
        }

        // Count total rows first - delegates to service for file processing
        try {
            $rowCount = $this->importService->countRows($fullPath);
        } catch (FileOperationException $e) {
            return back()->with('error', 'Could not read uploaded file.')->withInput();
        }

        // Guard against pathological uploads: cap the row count so a single
        // import cannot monopolise the queue worker for an unreasonable time.
        $maxRows = (int) config('transactions.batch.max_rows', 5000);
        if ($rowCount > $maxRows) {
            Storage::delete($path);

            return back()->with('error', "Import rejected: file contains {$rowCount} data rows (maximum allowed is {$maxRows}).")->withInput();
        }

        // Create import record with total_rows
        $import = TransactionImport::create([
            'imported_by' => auth()->id(),
            'filename' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'total_rows' => $rowCount,
            'status' => TransactionImportStatus::Pending->value,
        ]);

        // Queue the heavy processing. The uploaded file is already persisted
        // under storage/app/imports, so the job re-reads it from there.
        ProcessTransactionImportJob::dispatch($import);

        return redirect()->route('transactions.batch-upload.show', $import)
            ->with('success', "Import queued for processing ({$rowCount} rows). This page will refresh with progress.");
    }

    /**
     * Show import results
     */
    public function showImportResults(TransactionImport $import): View
    {
        if ($import->imported_by !== auth()->id()) {
            abort(403, 'Unauthorized. You can only view your own import results.');
        }

        return view('transactions.import-results', compact('import'));
    }

    /**
     * Download CSV template
     */
    public function downloadTemplate(): Response
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="transaction_template.csv"',
        ];

        $columns = config('cems.batch_import.columns');
        $sampleCurrencies = config('cems.batch_import.sample_currencies', ['USD']);

        $template = implode(',', $columns)."\n";
        $typeIdx = array_search('type', $columns);
        $currencyIdx = array_search('currency_code', $columns);
        $quantityIdx = array_search('quantity', $columns);

        foreach ($sampleCurrencies as $i => $currency) {
            $type = $i % 2 === 0 ? 'Buy' : 'Sell';
            $quantity = $i % 2 === 0 ? 1000 : 500;
            $row = array_fill(0, count($columns), '');
            $row[0] = '1';
            $row[$typeIdx] = $type;
            $row[$currencyIdx] = $currency;
            $row[$quantityIdx] = $quantity;
            $row[array_search('purpose', $columns)] = 'Sample';
            $row[array_search('source_of_funds', $columns)] = 'Sample';
            $row[array_search('till_id', $columns)] = 'MAIN';
            $template .= implode(',', $row)."\n";
        }

        return response($template, 200, $headers);
    }

    /**
     * Download import errors as CSV
     */
    public function downloadErrors(TransactionImport $import): RedirectResponse|BinaryFileResponse
    {
        if ($import->imported_by !== auth()->id()) {
            abort(403, 'Unauthorized. You can only view your own import errors.');
        }

        $errors = $import->getErrors();

        if (empty($errors)) {
            return back()->with('info', 'No errors to download for this import.');
        }

        $headers = ['Row', 'Data', 'Error'];
        $rows = [];
        foreach ($errors as $rowNumber => $error) {
            $rows[] = [
                $rowNumber,
                is_array($error['data'] ?? null) ? json_encode($error['data']) : ($error['data'] ?? ''),
                $error['message'] ?? 'Unknown error',
            ];
        }

        $filename = "import_errors_{$import->id}.csv";
        $filepath = $this->csvReportWriter->write($filename, $headers, $rows);

        return response()->download(Storage::path($filepath), $filename, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend();
    }
}
