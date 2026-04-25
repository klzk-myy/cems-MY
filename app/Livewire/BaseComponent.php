<?php

namespace App\Livewire;

use Livewire\Component;

abstract class BaseComponent extends Component
{
    protected function success(string $message): void
    {
        session()->flash('success', $message);
    }

    protected function error(string $message): void
    {
        session()->flash('error', $message);
    }

    protected function dispatchBrowserEvent(string $event, array $data = []): void
    {
        $this->dispatch('browserEvent', $event, $data);
    }
}
