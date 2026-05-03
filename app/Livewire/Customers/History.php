<?php

namespace App\Livewire\Customers;

use App\Livewire\BaseComponent;
use App\Models\Customer;
use Illuminate\View\View;

class History extends BaseComponent
{
    public ?Customer $customer = null;

    public function mount(Customer $customer): void
    {
        $this->customer = $customer->load('transactions');
    }

    public function render(): View
    {
        return view('livewire.customers.history');
    }
}
