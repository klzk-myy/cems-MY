<?php

namespace App\Services\Reporting;

use App\Models\Transaction;
use App\Services\System\MathService;

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
            ->when($filters['date_from'] ?? null, fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->when($filters['branch_id'] ?? null, fn ($q) => $q->where('branch_id', $filters['branch_id']))
            ->when($filters['type'] ?? null, fn ($q) => $q->where('type', $filters['type']))
            ->when($filters['status'] ?? null, fn ($q) => $q->where('status', $filters['status']));

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $rows = $transactions->map(fn ($t) => [
            'id' => $t->id,
            'date' => $t->created_at?->format('Y-m-d H:i'),
            'customer' => $t->customer?->full_name,
            'type' => $t->type,
            'currency' => $t->currency_code,
            'foreign_amount' => $t->amount_foreign,
            'rate' => $t->rate,
            'local_amount' => $t->amount_local,
            'status' => $t->status,
            'branch' => $t->branch?->name,
            'created_by' => $t->creator?->username,
        ])->toArray();

        $headers = ['ID', 'Date', 'Customer', 'Type', 'Currency', 'Foreign Amt', 'Rate', 'Local Amt', 'Status', 'Branch', 'Created By'];

        return $this->csvWriter->write(
            'transactions_'.now()->format('Ymd_His').'.csv',
            $headers,
            $rows
        );
    }
}
