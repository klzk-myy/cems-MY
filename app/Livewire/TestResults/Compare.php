<?php

namespace App\Livewire\TestResults;

use App\Livewire\BaseComponent;
use Illuminate\View\View;

class Compare extends BaseComponent
{
    public $testResults;

    public function mount(): void
    {
        $this->testResults = collect([]);
    }

    public function render(): View
    {
        return view('livewire.test-results.compare');
    }
}
