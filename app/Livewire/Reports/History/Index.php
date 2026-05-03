<?php

namespace App\Livewire\Reports\History;

use App\Livewire\BaseComponent;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public function render(): View
    {
        return view('livewire.reports.history.index');
    }
}
