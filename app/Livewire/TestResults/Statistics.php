<?php

namespace App\Livewire\TestResults;

use App\Livewire\BaseComponent;
use Illuminate\View\View;

class Statistics extends BaseComponent
{
    public function render(): View
    {
        return view('livewire.test-results.statistics');
    }
}
