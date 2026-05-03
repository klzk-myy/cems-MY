<?php

namespace App\Livewire\Counters\Emergency;

use App\Livewire\BaseComponent;
use App\Models\Counter;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public ?Counter $counter = null;

    public $session = null;

    public ?string $reason = null;

    public function mount(Counter $counter): void
    {
        $this->counter = $counter->load('session');
        $this->session = $this->counter->session;
    }

    public function emergencyClose(): mixed
    {
        $this->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->counter->emergencyClose($this->reason);

            $this->success('Emergency closure confirmed.');

            return $this->redirect(route('counters.index'));
        } catch (\Exception $e) {
            $this->error('Failed to close: '.$e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.counters.emergency.index');
    }
}
