<?php

namespace App\Http\Controllers;

use App\Enums\TransactionImportStatus;
use App\Http\Requests\BatchUploadRequest;
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

        // Get the full file path - use actual file path for testing, Storage::path otherwise
        $fullPath = $this->documentStorageService->exists($path) ? $this->documentStorageService->path($path) : $file->getRealPath();

        // If file still doesn't exist at Storage path, fall back to temp path
        if (! file_exists($fullPath)) {
            $fullPath = $file->getRealPath();
        }

        // Count total rows first
        $handle = fopen($fullPath, 'r');
        if (! $handle) {
            return back()->with('error', 'Could not read uploaded file.')->withInput();
        }

        $header = fgetcsv($handle);
        $rowCount = 0;
        while (fgetcsv($handle) !== false) {
            $rowCount++;
        }
        fclose($handle);

        // Create import record with total_rows
        $import = TransactionImport::create([
            'imported_by' => auth()->id(),
            'filename' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'total_rows' => $rowCount,
            'status' => TransactionImportStatus::Pending->value,
        ]);

        try {
            // Process import
            $this->importService->process($import, $fullPath);

            return redirect()->route('transactions.batch-upload.show', $import)
                ->with('success', "Import completed. {$import->success_count} transactions imported, {$import->error_count} errors.");
        } catch (\Exception $e) {
            $this->logger->error('Transaction import failed', ['exception' => $e, 'import_id' => $import->id]);
            $import->update([
                'status' => TransactionImportStatus::Failed->value,
                'completed_at' => now(),
            ]);

            return back()->with('error', 'Import failed: '.$e->getMessage());
        }
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

        $template = "customer_id,type,currency_code,amount_foreign,rate,purpose,source_of_funds,till_id\n";
        $template .= "1,Buy,USD,1000,4.72,Business Travel,Salary,MAIN\n";
        $template .= "1,Sell,USD,500,4.75,Personal Use,Savings,TILL-001\n";

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
        $filepath = app(CsvReportWriter::class)->write($filename, $headers, $rows);

        return response()->download(Storage::path($filepath), $filename, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend();
    }
}
