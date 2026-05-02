<?php

namespace App\Livewire\Customers;

use App\Livewire\BaseComponent;
use App\Models\Customer;
use Illuminate\View\View;

class Show extends BaseComponent
{
    public Customer $customer;

    public function mount(Customer $customer): void
    {
        $this->customer = $customer->load(['documents', 'transactions']);
    }

    public function render(): View
    {
        return view('livewire.customers.show');
    }
}
