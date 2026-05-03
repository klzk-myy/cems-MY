<?php

namespace App\Livewire\TestResults;

use App\Livewire\BaseComponent;
use App\Models\TestResult;
use Illuminate\View\View;

class Show extends BaseComponent
{
    public ?TestResult $testResult = null;

    public function mount(TestResult $testResult): void
    {
        $this->testResult = $testResult;
    }

    public function render(): View
    {
        return view('livewire.test-results.show');
    }
}
