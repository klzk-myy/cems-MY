<?php

namespace App\Livewire\Accounting\Ledger;

use App\Livewire\BaseComponent;
use App\Models\ChartOfAccount;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public array $accountsByType = [];

    public function mount(): void
    {
        $this->loadAccountsByType();
    }

    protected function loadAccountsByType(): void
    {
        $accounts = ChartOfAccount::where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $grouped = $accounts->groupBy('account_type');

        $this->accountsByType = [
            'Asset' => [],
            'Liability' => [],
            'Equity' => [],
            'Revenue' => [],
            'Expense' => [],
        ];

        foreach ($grouped as $type => $accountsGroup) {
            if (isset($this->accountsByType[$type])) {
                foreach ($accountsGroup as $account) {
                    $this->accountsByType[$type][] = [
                        'account_code' => $account->account_code,
                        'account_name' => $account->account_name,
                        'account_type' => $account->account_type,
                        'account_class' => $account->account_class,
                    ];
                }
            }
        }
    }

    public function render(): View
    {
        return view('livewire.accounting.ledger.index');
    }
}
