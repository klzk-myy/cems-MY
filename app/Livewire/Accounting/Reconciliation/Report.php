<?php

namespace App\Livewire\Accounting\Reconciliation;

use App\Livewire\BaseComponent;
use App\Models\BankReconciliation;
use App\Services\MathService;
use Illuminate\View\View;

class Report extends BaseComponent
{
    public ?string $statementDate = null;

    public ?string $accountCode = null;

    public array $report = [];

    public array $matchedItems = [];

    public array $unmatchedItems = [];

    public array $exceptions = [];

    public function mount(?string $statementDate = null, ?string $accountCode = null): void
    {
        $this->statementDate = $statementDate ?? request('statement_date', now()->toDateString());
        $this->accountCode = $accountCode ?? request('account_code');

        if ($this->statementDate) {
            $this->loadReport();
        }
    }

    protected function loadReport(): void
    {
        $mathService = new MathService;

        $query = BankReconciliation::query()
            ->whereDate('statement_date', $this->statementDate);

        if ($this->accountCode) {
            $query->where('account_code', $this->accountCode);
        }

        $items = $query->orderBy('statement_date')
            ->orderBy('created_at')
            ->get();

        $this->matchedItems = $items->where('status', 'matched')->map(function ($item) {
            return $this->formatReconciliationItem($item);
        })->toArray();

        $this->unmatchedItems = $items->where('status', 'unmatched')->map(function ($item) {
            return $this->formatReconciliationItem($item);
        })->toArray();

        $this->exceptions = $items->where('status', 'exception')->map(function ($item) {
            return $this->formatReconciliationItem($item);
        })->toArray();

        // Calculate totals
        $totalDebits = $items->sum('debit') ?? '0';
        $totalCredits = $items->sum('credit') ?? '0';

        $this->report = [
            'statement_date' => $this->statementDate,
            'account_code' => $this->accountCode,
            'total_items' => $items->count(),
            'matched_count' => count($this->matchedItems),
            'unmatched_count' => count($this->unmatchedItems),
            'exceptions_count' => count($this->exceptions),
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'net_amount' => $mathService->subtract($totalDebits, $totalCredits),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function formatReconciliationItem(BankReconciliation $item): array
    {
        return [
            'id' => $item->id,
            'statement_date' => $item->statement_date?->format('Y-m-d'),
            'reference' => $item->reference,
            'description' => $item->description,
            'debit' => $item->debit,
            'credit' => $item->credit,
            'amount' => $item->getAmount(),
            'status' => $item->status,
            'matched_to_journal_entry_id' => $item->matched_to_journal_entry_id,
            'matched_at' => $item->matched_at?->toIso8601String(),
            'check_number' => $item->check_number,
            'check_date' => $item->check_date?->format('Y-m-d'),
            'check_status' => $item->check_status,
            'check_payee' => $item->check_payee,
            'notes' => $item->notes,
        ];
    }

    public function generateReport(): void
    {
        $this->loadReport();
    }

    public function updatedStatementDate(): void
    {
        $this->loadReport();
    }

    public function updatedAccountCode(): void
    {
        $this->loadReport();
    }

    public function render(): View
    {
        return view('livewire.accounting.reconciliation.report');
    }
}
