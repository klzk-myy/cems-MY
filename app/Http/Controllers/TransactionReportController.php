<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SystemLog;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionReportController extends Controller
{
    public function __construct(
        protected ExportService $exportService
    ) {}

    /**
     * Display customer transaction history with filtering and pagination.
     *
     * @return \Illuminate\View\View
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
        $stats = $this->calculateStats($customer, $validated);
        $chartData = $this->calculateChartData($customer, $validated);

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
            ['stats' => $stats],
            $chartData
        ));
    }

    /**
     * Export customer transaction history to CSV or PDF.
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response
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

        // Prepare export data
        $exportData = $this->prepareExportData($transactions, $customer);

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
     * Calculate statistics for customer transactions.
     */
    protected function calculateStats(Customer $customer, array $filters): array
    {
        $query = $customer->transactions();

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $transactions = $query->get();

        $buyTransactions = $transactions->where('type', \App\Enums\TransactionType::Buy);
        $sellTransactions = $transactions->where('type', \App\Enums\TransactionType::Sell);

        $buyVolume = $buyTransactions->sum('amount_local');
        $sellVolume = $sellTransactions->sum('amount_local');
        $totalVolume = $buyVolume + $sellVolume;
        $totalCount = $transactions->count();

        return [
            'total_count' => $totalCount,
            'buy_count' => $buyTransactions->count(),
            'sell_count' => $sellTransactions->count(),
            'buy_volume' => $buyVolume,
            'sell_volume' => $sellVolume,
            'total_volume' => $totalVolume,
            'avg_transaction' => $totalCount > 0 ? $totalVolume / $totalCount : 0,
            'first_transaction' => $transactions->min('created_at'),
            'last_transaction' => $transactions->max('created_at'),
        ];
    }

    /**
     * Calculate chart data for customer transactions.
     */
    protected function calculateChartData(Customer $customer, array $filters): array
    {
        // Get all transactions and aggregate in PHP for database compatibility
        $query = $customer->transactions()
            ->select('created_at', 'type', 'amount_local');

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $transactions = $query->get();

        // Get last 12 months of labels
        $chartLabels = [];
        $chartBuyData = [];
        $chartSellData = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $chartLabels[] = $date->format('M Y');

            $monthTransactions = $transactions->filter(function ($t) use ($date) {
                return $t->created_at->year === $date->year && $t->created_at->month === $date->month;
            });

            $buyTotal = $monthTransactions->where('type', \App\Enums\TransactionType::Buy)->sum('amount_local');
            $sellTotal = $monthTransactions->where('type', \App\Enums\TransactionType::Sell)->sum('amount_local');

            $chartBuyData[] = $buyTotal ?: 0;
            $chartSellData[] = $sellTotal ?: 0;
        }

        return [
            'chartLabels' => $chartLabels,
            'chartBuyData' => $chartBuyData,
            'chartSellData' => $chartSellData,
        ];
    }

    /**
     * Calculate summary statistics for customer transactions.
     *
     * @deprecated Use calculateStats() instead
     */
    protected function calculateSummary(Customer $customer, array $filters): array
    {
        return $this->calculateStats($customer, $filters);
    }

    /**
     * Prepare transaction data for export.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $transactions
     */
    protected function prepareExportData($transactions, Customer $customer): array
    {
        return $transactions->map(function ($transaction) {
            return [
                'Transaction ID' => $transaction->id,
                'Date' => $transaction->created_at->format('Y-m-d H:i:s'),
                'Type' => $transaction->type->label(),
                'Currency' => $transaction->currency_code,
                'Foreign Amount' => $transaction->amount_foreign,
                'MYR Amount' => $transaction->amount_local,
                'Rate' => $transaction->rate,
                'Status' => $transaction->status->label(),
                'Processed By' => $transaction->user?->name ?? 'N/A',
                'Purpose' => $transaction->purpose ?? 'N/A',
                'Source of Funds' => $transaction->source_of_funds ?? 'N/A',
                'CDD Level' => $transaction->cdd_level?->label() ?? 'N/A',
            ];
        })->toArray();
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
     * @return \Illuminate\Http\Response
     */
    protected function exportToPdf(array $data, string $filename, Customer $customer, array $filters)
    {
        $pdf = app('dompdf.wrapper');
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
