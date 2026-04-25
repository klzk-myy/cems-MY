<?php

namespace App\Livewire\Accounting;

use App\Livewire\BaseComponent;
use App\Services\FinancialRatioService;
use Illuminate\View\View;

class Ratios extends BaseComponent
{
    public string $fromDate;

    public string $toDate;

    public string $asOfDate;

    public array $ratios = [];

    public bool $hasData = false;

    public function mount(?string $fromDate = null, ?string $toDate = null, ?string $asOfDate = null)
    {
        $this->fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
        $this->toDate = $toDate ?? now()->toDateString();
        $this->asOfDate = $asOfDate ?? now()->toDateString();

        if ($fromDate && $toDate) {
            $this->loadRatios();
        }
    }

    protected function loadRatios(): void
    {
        $ratioService = app(FinancialRatioService::class);
        $this->ratios = $ratioService->getAllRatios($this->asOfDate, $this->fromDate, $this->toDate);
        $this->hasData = true;
    }

    public function updatedFromDate(): void
    {
        if ($this->fromDate && $this->toDate) {
            $this->loadRatios();
        }
    }

    public function updatedToDate(): void
    {
        if ($this->fromDate && $this->toDate) {
            $this->loadRatios();
        }
    }

    public function render(): View
    {
        return view('livewire.accounting.ratios');
    }
}
