<?php

namespace App\Livewire\Accounting;

use App\Livewire\BaseComponent;
use App\Services\LedgerService;
use Illuminate\View\View;

class BalanceSheet extends BaseComponent
{
    public string $asOfDate;

    public array $balanceSheet = [];

    public function mount(?string $asOfDate = null)
    {
        $this->asOfDate = $asOfDate ?? now()->toDateString();
        $this->loadBalanceSheet();
    }

    protected function loadBalanceSheet(): void
    {
        $ledgerService = app(LedgerService::class);
        $this->balanceSheet = $ledgerService->getBalanceSheet($this->asOfDate);
    }

    public function updatedAsOfDate(): void
    {
        $this->loadBalanceSheet();
    }

    public function render(): View
    {
        return view('livewire.accounting.balance-sheet');
    }
}
