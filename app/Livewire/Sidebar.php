<?php

namespace App\Livewire;

use App\Models\Alert;
use Illuminate\Database\Eloquent\Collection;

class Sidebar extends BaseComponent
{
    public string $activeRoute = '';

    public ?Collection $pendingAlerts = null;

    public function mount()
    {
        $this->activeRoute = request()->path();
        $this->pendingAlerts = Alert::whereNull('assigned_to')
            ->whereNull('case_id')
            ->get();
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
