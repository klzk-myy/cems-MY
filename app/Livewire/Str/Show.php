<?php

namespace App\Livewire\Str;

use App\Livewire\BaseComponent;
use App\Models\Str;
use Illuminate\View\View;

class Show extends BaseComponent
{
    public ?Str $str = null;

    public function mount(Str $str): void
    {
        $this->str = $str;
    }

    public function render(): View
    {
        return view('livewire.str.show');
    }
}
