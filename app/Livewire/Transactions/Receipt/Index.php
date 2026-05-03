<?php

namespace App\Livewire\Transactions\Receipt;

use App\Livewire\BaseComponent;
use App\Models\Transaction;
use Illuminate\View\View;

class Index extends BaseComponent
{
    public ?Transaction $transaction = null;

    public function mount(Transaction $transaction): void
    {
        $this->transaction = $transaction->load([
            'customer',
            'user',
            'approver',
            'counter',
            'branch',
        ]);
    }

    public function render(): View
    {
        return view('livewire.transactions.receipt.index');
    }
}
