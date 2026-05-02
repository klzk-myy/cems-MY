<?php

namespace App\Livewire\BranchOpenings;

use App\Livewire\BaseComponent;
use App\Models\Branch;
use Illuminate\View\View;

class Complete extends BaseComponent
{
    public Branch $branch;

    public array $stats = [];

    public function mount(Branch $branch): void
    {
        $this->branch = $branch;
        $this->stats = [
            'pool_count' => $branch->branchPools()->count(),
            'total_currencies' => $branch->branchPools()->where('available_balance', '>', 0)->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.branch-openings.complete');
    }
}
