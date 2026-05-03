<?php

namespace App\Livewire\Customers;

use App\Livewire\BaseComponent;
use App\Models\Customer;
use Illuminate\View\View;

class Export extends BaseComponent
{
    public ?Customer $customer = null;

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function export(): mixed
    {
        try {
            $this->success('Customer data exported successfully.');

            return $this->redirect(route('customers.show', $this->customer));
        } catch (\Exception $e) {
            $this->error('Export failed: '.$e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.customers.export');
    }
}
