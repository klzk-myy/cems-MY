<?php

namespace App\Livewire\Reports\Analytics;

use App\Livewire\BaseComponent;
use Illuminate\View\View;

class CustomerAnalysis extends BaseComponent
{
    public function render(): View
    {
        return view('livewire.reports.analytics.customer-analysis');
    }
}
