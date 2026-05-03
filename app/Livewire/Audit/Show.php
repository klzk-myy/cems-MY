<?php

namespace App\Livewire\Audit;

use App\Livewire\BaseComponent;
use App\Models\Audit;
use Illuminate\View\View;

class Show extends BaseComponent
{
    public ?Audit $audit = null;

    public function mount(Audit $audit): void
    {
        $this->audit = $audit;
    }

    public function render(): View
    {
        return view('livewire.audit.show');
    }
}
