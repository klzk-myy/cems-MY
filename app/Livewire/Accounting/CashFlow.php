<?php

namespace App\Livewire\Accounting;

use App\Livewire\BaseComponent;
use App\Services\CashFlowService;
use Illuminate\View\View;

class CashFlow extends BaseComponent
{
    public string $fromDate;

    public string $toDate;

    public array $cashFlow = [];

    public bool $hasData = false;

    public function mount(?string $fromDate = null, ?string $toDate = null)
    {
        $this->fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
        $this->toDate = $toDate ?? now()->toDateString();

        if ($fromDate && $toDate) {
            $this->loadCashFlow();
        }
    }

    protected function loadCashFlow(): void
    {
        $cashFlowService = app(CashFlowService::class);
        $this->cashFlow = $cashFlowService->getCashFlowStatement($this->fromDate, $this->toDate);
        $this->hasData = true;
    }

    public function updatedFromDate(): void
    {
        if ($this->fromDate && $this->toDate) {
            $this->loadCashFlow();
        }
    }

    public function updatedToDate(): void
    {
        if ($this->fromDate && $this->toDate) {
            $this->loadCashFlow();
        }
    }

    public function render(): View
    {
        return view('livewire.accounting.cash-flow');
    }
}
