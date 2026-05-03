<?php

namespace App\Livewire\Accounting\Revaluation;

use App\Livewire\BaseComponent;
use Illuminate\View\View;

class History extends BaseComponent
{
    public function render(): View
    {
        return view('livewire.accounting.revaluation.history');
    }
}
