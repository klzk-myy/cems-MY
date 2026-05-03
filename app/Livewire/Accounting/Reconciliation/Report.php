<?php

namespace App\Livewire\Accounting\Reconciliation;

use App\Livewire\BaseComponent;
use Illuminate\View\View;

class Report extends BaseComponent
{
    public function render(): View
    {
        return view('livewire.accounting.reconciliation.report');
    }
}
