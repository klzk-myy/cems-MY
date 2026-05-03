<?php

namespace App\Livewire\Counters\Emergency;

use App\Livewire\BaseComponent;
use App\Models\Counter;
use Illuminate\View\View;

class Closure extends BaseComponent
{
    public ?Counter $counter = null;

    public $closure = null;

    public array $variance = [];

    public function mount(Counter $counter): void
    {
        $this->counter = $counter->load('closure');
        $this->closure = $this->counter->closure;
    }

    public function acknowledge(): mixed
    {
        try {
            $this->success('Emergency closure acknowledged.');

            return $this->redirect(route('counters.index'));
        } catch (\Exception $e) {
            $this->error('Failed to acknowledge: '.$e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.counters.emergency.closure');
    }
}
