<?php

namespace App\Services\Reporting;

use App\Models\Transaction;
use App\Services\System\MathService;
use Carbon\Carbon;

class TransactionExportService
{
    public function __construct(
        protected MathService $mathService,
        protected CsvReportWriter $csvWriter,
    ) {}

    /**
     * Export transactions to CSV within a date range.
     *
     * @return string The file path of the generated CSV
     */
    public function exportTransactions(array $filters, int $userId): string
    {
        $query = Transaction::with(['customer', 'branch', 'creator'])
            ->when($filters['date_from'] ?? null, fn ($q) => $q->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($q) => $q->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay()))
            ->when($filters['branch_id'] ?? null, fn ($q) => $q->where('branch_id', $filters['branch_id']))
            ->when($filters['type'] ?? null, fn ($q) => $q->where('type', $filters['type']))
            ->when($filters['status'] ?? null, fn ($q) => $q->where('status', $filters['status']));

        // Chunked export: a wide date range can match hundreds of thousands of
        // rows — materializing them via ->get() would exhaust memory. Keyset
        // chunks on the id column (monotonic with created_at) keep the
        // newest-first order while staying stable when transactions are
        // inserted mid-export — forPage offsets would skip/duplicate rows.
        // writeStreaming expects positional rows (array<int, mixed>); the map
        // builds named pairs for readability and array_values() flattens them.
        $rows = $query->lazyByIdDesc(1000)->map(fn (Transaction $t) => array_values([
            'id' => $t->id,
            'date' => $t->created_at?->format('Y-m-d H:i'),
            'customer' => $t->customer?->full_name,
            'type' => $t->type,
            'currency' => $t->currency_code,
            'foreign_amount' => $t->quantity,
            'rate' => $t->rate,
            'local_amount' => $t->amount_myr,
            'status' => $t->status,
            'branch' => $t->branch?->name,
            'created_by' => $t->creator?->username,
        ]));

        $headers = ['ID', 'Date', 'Customer', 'Type', 'Currency', 'Foreign Amt', 'Rate', 'Local Amt', 'Status', 'Branch', 'Created By'];

        return $this->csvWriter->writeStreaming(
            'transactions_'.now()->format('Ymd_His').'.csv',
            $headers,
            $rows
        );
    }
}
