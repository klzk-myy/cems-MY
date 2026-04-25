<?php

namespace App\Livewire\Accounting;

use App\Livewire\BaseComponent;
use App\Models\JournalEntry;
use App\Services\LedgerService;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public array $summary = [];

    public array $recentEntries = [];

    public function mount(): void
    {
        $this->loadSummary();
        $this->loadRecentEntries();
    }

    protected function loadSummary(): void
    {
        $ledgerService = app(LedgerService::class);

        // Get balance sheet summary
        $asOfDate = now()->toDateString();

        // Total Assets (debit-normal accounts with positive balances)
        $totalAssets = $this->getTotalByType('Asset', $asOfDate);

        // Total Liabilities (credit-normal accounts with positive balances)
        $totalLiabilities = $this->getTotalByType('Liability', $asOfDate);

        // Total Equity (credit-normal accounts)
        $totalEquity = $this->getTotalByType('Equity', $asOfDate);

        // Revenue (YTD)
        $revenue = $this->getTotalByType('Revenue', $asOfDate, true);

        // Expenses (YTD)
        $expenses = $this->getTotalByType('Expense', $asOfDate, true);

        $this->summary = [
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'revenue' => $revenue,
            'expenses' => $expenses,
        ];
    }

    protected function getTotalByType(string $accountType, string $asOfDate, bool $ytd = false): string
    {
        $ledgerService = app(LedgerService::class);
        $trialBalance = $ledgerService->getTrialBalance($asOfDate);

        $total = '0';
        foreach ($trialBalance['accounts'] as $account) {
            if ($account['account_type'] === $accountType) {
                $balance = $account['balance'] ?? '0';
                if ($accountType === 'Asset' || $accountType === 'Expense') {
                    // Debit-normal accounts
                    $total = bcadd($total, $balance, 4);
                } else {
                    // Credit-normal accounts (Liability, Equity, Revenue)
                    $total = bcadd($total, $balance, 4);
                }
            }
        }

        return $total;
    }

    protected function loadRecentEntries(): void
    {
        $this->recentEntries = JournalEntry::with('lines')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'entry_date' => $entry->entry_date?->format('d M Y') ?? 'N/A',
                    'entry_number' => 'JE-'.str_pad($entry->id, 6, '0', STR_PAD_LEFT),
                    'description' => $entry->description,
                    'status' => $entry->status?->value ?? 'Draft',
                    'status_label' => $entry->status?->label() ?? 'Draft',
                    'lines_count' => $entry->lines->count(),
                    'total_debit' => $entry->getTotalDebits(),
                    'is_posted' => $entry->isPosted(),
                    'is_draft' => $entry->isDraft(),
                    'is_pending' => $entry->isPending(),
                    'is_reversed' => $entry->isReversed(),
                ];
            })
            ->toArray();
    }

    public function render(): View
    {
        return view('livewire.accounting.index');
    }
}
