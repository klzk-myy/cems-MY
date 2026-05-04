<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SystemLog;
use App\Services\CustomerReportService;
use App\Services\ExportService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionReportController extends Controller
{
    public function __construct(
        protected ExportService $exportService,
        protected CustomerReportService $customerReportService,
        private PDF $pdf
    ) {}

    /**
     * Display customer transaction history with filtering and pagination.
     *
     * @return View
     */
    public function customerHistory(Request $request, Customer $customer)
    {
        // Validate date range filters
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort_by' => 'nullable|in:date,amount',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        // Build query with eager loaded relationships
        $query = $customer->transactions()
            ->with(['user', 'currency', 'flags']);

        // Apply date range filter
        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'date';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        switch ($sortBy) {
            case 'amount':
                $query->orderBy('amount_local', $sortOrder);
                break;
            case 'date':
            default:
                $query->orderBy('created_at', $sortOrder);
                break;
        }

        // Paginate results
        $transactions = $query->paginate(20)->withQueryString();

        // Calculate stats and chart data
        $summary = $this->customerReportService->getTransactionSummary($customer, $validated);

        // Log access for audit trail
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'customer_history_viewed',
            'entity_type' => 'Customer',
            'entity_id' => $customer->id,
            'new_values' => [
                'customer_name' => $customer->full_name,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'record_count' => $transactions->total(),
            ],
            'ip_address' => $request->ip(),
        ]);

        return view('transactions.customer-history', array_merge(
            compact('customer', 'transactions', 'validated'),
            ['stats' => $summary['stats']],
            $summary['chart']
        ));
    }

    /**
     * Export customer transaction history to CSV or PDF.
     *
     * @return BinaryFileResponse|Response
     */
    public function exportCustomerHistory(Request $request, Customer $customer)
    {
        // Validate export parameters
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort_by' => 'nullable|in:date,amount',
            'sort_order' => 'nullable|in:asc,desc',
            'format' => 'nullable|in:CSV,PDF',
            'export' => 'nullable',
            'limit' => 'nullable|integer',
        ]);

        // Default to CSV if format not specified
        if (empty($validated['format'])) {
            $validated['format'] = 'CSV';
        }

        // Build query
        $query = $customer->transactions()
            ->with(['user', 'currency']);

        // Apply date range filter
        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'date';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        switch ($sortBy) {
            case 'amount':
                $query->orderBy('amount_local', $sortOrder);
                break;
            case 'date':
            default:
                $query->orderBy('created_at', $sortOrder);
                break;
        }

        // Get all records for export (no pagination)
        $transactions = $query->get();

        // Prepare export data using service
        $exportData = $this->customerReportService->prepareExportData($transactions);

        // Generate filename
        $timestamp = now()->format('Ymd_His');
        $filename = "customer_{$customer->id}_history_{$timestamp}";

        // Log export for audit trail
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'customer_history_exported',
            'entity_type' => 'Customer',
            'entity_id' => $customer->id,
            'new_values' => [
                'customer_name' => $customer->full_name,
                'format' => $validated['format'],
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'record_count' => count($exportData),
            ],
            'ip_address' => $request->ip(),
        ]);

        // Generate export based on format
        switch ($validated['format']) {
            case 'CSV':
                return $this->exportToCsv($exportData, $filename, $customer);
            case 'PDF':
                return $this->exportToPdf($exportData, $filename, $customer, $validated);
            default:
                abort(400, 'Invalid export format');
        }
    }

    /**
     * Calculate summary statistics for customer transactions.
     *
     * @deprecated Use CustomerReportService::calculateStats() instead
     */
    protected function calculateSummary(Customer $customer, array $filters): array
    {
        return $this->customerReportService->calculateStats($customer, $filters);
    }

    /**
     * Export data to CSV format with streaming response.
     */
    protected function exportToCsv(array $data, string $filename, Customer $customer): StreamedResponse
    {
        $fullFilename = $filename.'.csv';

        $response = new StreamedResponse(function () use ($data, $customer) {
            $handle = fopen('php://output', 'w');

            // Add header section with customer info
            fputcsv($handle, ['Customer Transaction History Report']);
            fputcsv($handle, ['Generated', now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, ['Customer', $customer->full_name]);
            fputcsv($handle, ['Customer ID', $customer->id]);
            fputcsv($handle, []);

            // Add column headers
            if (! empty($data)) {
                fputcsv($handle, array_keys($data[0]));

                // Add data rows
                foreach ($data as $row) {
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$fullFilename.'"');

        return $response;
    }

    /**
     * Export data to PDF format.
     *
     * @return Response
     */
    protected function exportToPdf(array $data, string $filename, Customer $customer, array $filters)
    {
        $pdf = $this->pdf;
        $pdf->loadView('transactions.export.customer-history-pdf', [
            'data' => $data,
            'customer' => $customer,
            'filters' => $filters,
            'generatedAt' => now(),
        ]);

        $fullFilename = $filename.'.pdf';

        return $pdf->download($fullFilename);
    }
}
