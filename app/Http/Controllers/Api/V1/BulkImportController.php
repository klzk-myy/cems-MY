<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCustomerImport;
use App\Jobs\ProcessTransactionImport;
use App\Services\BulkImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bulk Import Controller
 *
 * Handles bulk import operations for customers and transactions via CSV upload.
 */
class BulkImportController extends Controller
{
    public function __construct(
        protected BulkImportService $importService
    ) {}

    /**
     * Upload and import customers from CSV.
     *
     * POST /api/v1/import/customers
     */
    public function importCustomers(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
        ]);

        $file = $request->file('file');
        $jobId = Str::uuid()->toString();

        // Store file
        $path = $file->storeAs('imports/customers', "{$jobId}.csv");

        // Dispatch job
        ProcessCustomerImport::dispatch($path, auth()->id(), $jobId);

        Log::info('Customer import initiated', [
            'job_id' => $jobId,
            'user_id' => auth()->id(),
            'file' => $path,
        ]);

        return response()->json([
            'message' => 'Customer import job queued',
            'job_id' => $jobId,
            'status_url' => "/api/v1/import/status/{$jobId}",
        ], 202);
    }

    /**
     * Upload and import transactions from CSV.
     *
     * POST /api/v1/import/transactions
     */
    public function importTransactions(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
        ]);

        $file = $request->file('file');
        $jobId = Str::uuid()->toString();

        // Store file
        $path = $file->storeAs('imports/transactions', "{$jobId}.csv");

        // Dispatch job
        ProcessTransactionImport::dispatch($path, auth()->id(), $jobId);

        Log::info('Transaction import initiated', [
            'job_id' => $jobId,
            'user_id' => auth()->id(),
            'file' => $path,
        ]);

        return response()->json([
            'message' => 'Transaction import job queued',
            'job_id' => $jobId,
            'status_url' => "/api/v1/import/status/{$jobId}",
        ], 202);
    }

    /**
     * Get import job status.
     *
     * GET /api/v1/import/status/{jobId}
     */
    public function getStatus(string $jobId): JsonResponse
    {
        $status = $this->importService->getImportStatus($jobId);

        if ($status === null) {
            return response()->json([
                'error' => 'Import job not found',
                'job_id' => $jobId,
            ], 404);
        }

        return response()->json([
            'job_id' => $jobId,
            'status' => $status,
        ]);
    }

    /**
     * Get import errors.
     *
     * GET /api/v1/import/errors/{jobId}
     */
    public function getErrors(string $jobId): JsonResponse
    {
        $status = $this->importService->getImportStatus($jobId);

        if ($status === null) {
            return response()->json([
                'error' => 'Import job not found',
                'job_id' => $jobId,
            ], 404);
        }

        return response()->json([
            'job_id' => $jobId,
            'errors' => $status['errors'] ?? [],
            'error_count' => $status['error_count'] ?? 0,
        ]);
    }
}
