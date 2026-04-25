<?php

namespace App\Livewire;

use Livewire\Component;

class Sidebar extends Component
{
    public string $activeRoute = '';

    public function mount()
    {
        $this->activeRoute = request()->path();
    }

    public function isActive(string $path): bool
    {
        return str_starts_with($this->activeRoute, $path);
    }

    public function render()
    {
        return view('livewire.layout.sidebar');
    }
}
