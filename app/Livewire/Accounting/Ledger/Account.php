<?php

namespace App\Livewire\Accounting\Ledger;

use App\Livewire\BaseComponent;
use App\Services\LedgerService;
use Illuminate\View\View;

class Account extends BaseComponent
{
    public string $accountCode;

    public string $dateFrom;

    public string $dateTo;

    public array $ledger = [];

    public array $accountInfo = [];

    public function mount(string $accountCode): void
    {
        $this->accountCode = $accountCode;

        // Default date range: current month
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();

        $this->loadLedger();
    }

    protected function loadLedger(): void
    {
        $ledgerService = app(LedgerService::class);

        try {
            $data = $ledgerService->getAccountLedger(
                $this->accountCode,
                $this->dateFrom,
                $this->dateTo
            );

            $this->accountInfo = [
                'account_code' => $data['account']->account_code,
                'account_name' => $data['account']->account_name,
                'account_type' => $data['account']->account_type,
                'opening_balance' => $data['opening_balance'],
                'closing_balance' => $data['closing_balance'],
                'total_debits' => $data['total_debits'],
                'total_credits' => $data['total_credits'],
                'period_from' => $data['period']['from'],
                'period_to' => $data['period']['to'],
            ];

            $this->ledger = $data['entries']->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'entry_date' => $entry->entry_date?->format('d M Y') ?? 'N/A',
                    'entry_date_raw' => $entry->entry_date?->toDateString() ?? '',
                    'journal_entry_id' => $entry->journal_entry_id,
                    'description' => $entry->description ?? 'N/A',
                    'debit' => $entry->debit,
                    'credit' => $entry->credit,
                    'running_balance' => $entry->running_balance,
                    'branch_id' => $entry->branch_id,
                ];
            })->toArray();
        } catch (\Exception $e) {
            $this->ledger = [];
            $this->accountInfo = [
                'account_code' => $this->accountCode,
                'account_name' => 'Unknown Account',
                'account_type' => '',
                'opening_balance' => '0',
                'closing_balance' => '0',
                'total_debits' => '0',
                'total_credits' => '0',
                'period_from' => $this->dateFrom,
                'period_to' => $this->dateTo,
            ];
        }
    }

    public function applyDateFilter(): void
    {
        $this->loadLedger();
    }

    public function render(): View
    {
        return view('livewire.accounting.ledger.account');
    }
}
