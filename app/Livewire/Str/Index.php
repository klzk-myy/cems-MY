<?php

namespace App\Livewire\Str;

use App\Livewire\BaseComponent;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public function render(): View
    {
        return view('livewire.str.index');
    }
}
