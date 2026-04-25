<?php

namespace App\Livewire\Accounting;

use App\Livewire\BaseComponent;
use App\Services\LedgerService;
use Illuminate\View\View;

class TrialBalance extends BaseComponent
{
    public string $asOfDate;

    public array $trialBalance = [];

    public function mount(?string $asOfDate = null)
    {
        $this->asOfDate = $asOfDate ?? now()->toDateString();
        $this->loadTrialBalance();
    }

    protected function loadTrialBalance(): void
    {
        $ledgerService = app(LedgerService::class);
        $this->trialBalance = $ledgerService->getTrialBalance($this->asOfDate);
    }

    public function updatedAsOfDate(): void
    {
        $this->loadTrialBalance();
    }

    public function render(): View
    {
        return view('livewire.accounting.trial-balance');
    }
}
