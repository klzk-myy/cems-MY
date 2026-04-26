<?php

namespace App\Livewire\Branches;

use App\Livewire\BaseComponent;
use App\Models\Branch;
use Illuminate\View\View;

class Show extends BaseComponent
{
    public Branch $branch;

    public array $stats = [];

    public array $childBranches = [];

    public function mount(Branch $branch): void
    {
        $this->branch = $branch->load(['parent', 'users', 'counters']);
        $this->loadStats();
        $this->loadChildBranches();
    }

    protected function loadStats(): void
    {
        $this->stats = [
            'user_count' => $this->branch->users()->count(),
            'counter_count' => $this->branch->counters()->count(),
            'transaction_today' => $this->branch->transactions()
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            'transaction_month' => $this->branch->transactions()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
    }

    protected function loadChildBranches(): void
    {
        $this->childBranches = $this->branch->children()->get()->map(function ($child) {
            return [
                'id' => $child->id,
                'code' => $child->code,
                'name' => $child->name,
                'type' => $child->type,
                'is_active' => $child->is_active,
            ];
        })->toArray();
    }

    public function render(): View
    {
        return view('livewire.branches.show');
    }
}
