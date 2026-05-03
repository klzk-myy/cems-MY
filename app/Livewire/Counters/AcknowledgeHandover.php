<?php

namespace App\Livewire\Counters;

use App\Livewire\BaseComponent;
use App\Models\Counter;
use Illuminate\View\View;

class AcknowledgeHandover extends BaseComponent
{
    public ?Counter $counter = null;

    public $handover = null;

    public bool $verified = false;

    public ?string $notes = null;

    public function mount(Counter $counter): void
    {
        $this->counter = $counter->load('handover');
        $this->handover = $this->counter->handover;
    }

    public function acknowledge(): mixed
    {
        $this->validate([
            'verified' => 'accepted',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->handover->acknowledge($this->notes);

            $this->success('Handover acknowledged.');

            return $this->redirect(route('counters.index'));
        } catch (\Exception $e) {
            $this->error('Failed to acknowledge: '.$e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.counters.acknowledge-handover');
    }
}
