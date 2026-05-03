<?php

namespace App\Livewire\Transactions;

use App\Livewire\BaseComponent;
use Illuminate\View\View;

class BatchUpload extends BaseComponent
{
    public function render(): View
    {
        return view('livewire.transactions.batch-upload');
    }
}
