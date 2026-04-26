<?php

namespace App\Livewire\BranchOpenings;

use App\Livewire\BaseComponent;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public function render(): View
    {
        return view('livewire.branch-openings.index');
    }
}
