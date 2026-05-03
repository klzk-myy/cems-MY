<?php

namespace App\Livewire\Accounting\FiscalYears;

use App\Livewire\BaseComponent;
use App\Models\FiscalYear;
use Illuminate\View\View;

class Report extends BaseComponent
{
    public ?FiscalYear $fiscalYear = null;

    public function mount(FiscalYear $yearCode): void
    {
        $this->fiscalYear = $yearCode;
    }

    public function render(): View
    {
        return view('livewire.accounting.fiscal-years.report');
    }
}
