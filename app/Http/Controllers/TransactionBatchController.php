<?php

namespace App\Http\Controllers;

use App\Models\TransactionImport;
use App\Services\AccountingService;
use App\Services\ComplianceService;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use App\Services\TransactionImportService;
use App\Services\TransactionMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TransactionBatchController extends Controller
{
    public function __construct(
        protected MathService $mathService,
        protected ComplianceService $complianceService,
        protected CurrencyPositionService $positionService,
        protected AccountingService $accountingService,
        protected TransactionMonitoringService $monitoringService
    ) {}

    /**
     * Show batch upload form
     */
    public function showBatchUpload()
    {
        $recentImports = TransactionImport::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return view('transactions.batch-upload', compact('recentImports'));
    }

    /**
     * Process batch upload
     */
    public function processBatchUpload(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('csv_file');

        // Store file
        $path = $file->store('imports');

        // Get the full file path - use actual file path for testing, Storage::path otherwise
        $fullPath = Storage::exists($path) ? Storage::path($path) : $file->getRealPath();

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
            'user_id' => auth()->id(),
            'filename' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'total_rows' => $rowCount,
            'status' => 'pending',
        ]);

        try {
            // Process import
            $service = new TransactionImportService(
                $import,
                $this->mathService,
                $this->complianceService,
                $this->positionService,
                $this->accountingService,
                $this->monitoringService
            );
            $service->process($fullPath);

            return redirect()->route('transactions.batch-upload.show', $import)
                ->with('success', "Import completed. {$import->success_count} transactions imported, {$import->error_count} errors.");
        } catch (\Exception $e) {
            Log::error('Transaction import failed', ['exception' => $e, 'import_id' => $import->id]);
            $import->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            return back()->with('error', 'Import failed: '.$e->getMessage());
        }
    }

    /**
     * Show import results
     */
    public function showImportResults(TransactionImport $import)
    {
        // Authorization check - only owner can view (managers can only view their own imports)
        if ($import->user_id !== auth()->id()) {
            abort(403, 'Unauthorized. You can only view your own import results.');
        }

        return view('transactions.import-results', compact('import'));
    }

    /**
     * Download CSV template
     */
    public function downloadTemplate()
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
}
