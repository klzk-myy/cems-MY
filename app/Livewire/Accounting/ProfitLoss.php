<?php

namespace App\Livewire\Accounting;

use App\Livewire\BaseComponent;
use App\Services\LedgerService;
use Illuminate\View\View;

class ProfitLoss extends BaseComponent
{
    public string $fromDate;

    public string $toDate;

    public array $pl = [];

    public function mount(?string $fromDate = null, ?string $toDate = null)
    {
        $this->fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
        $this->toDate = $toDate ?? now()->toDateString();
        $this->loadProfitLoss();
    }

    protected function loadProfitLoss(): void
    {
        $ledgerService = app(LedgerService::class);
        $this->pl = $ledgerService->getProfitAndLoss($this->fromDate, $this->toDate);
    }

    public function updatedFromDate(): void
    {
        $this->loadProfitLoss();
    }

    public function updatedToDate(): void
    {
        $this->loadProfitLoss();
    }

    public function render(): View
    {
        return view('livewire.accounting.profit-loss');
    }
}
