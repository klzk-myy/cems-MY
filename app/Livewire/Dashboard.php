<?php

namespace App\Livewire;

use Illuminate\View\View;

class Dashboard extends BaseComponent
{
    public array $stats = [];

    public $recent_transactions;

    public function mount(): void
    {
        $this->stats = [
            'total_transactions' => 0,
            'buy_volume' => 0,
            'sell_volume' => 0,
            'flagged' => 0,
        ];
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
