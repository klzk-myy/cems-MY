<?php

namespace App\Livewire\Customers;

use App\Livewire\BaseComponent;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\View\View;

class Edit extends BaseComponent
{
    public Customer $customer;

    public string $fullName = '';

    public string $idType = '';

    public string $nationality = '';

    public ?string $address = null;

    public ?string $phone = null;

    public ?string $email = null;

    public bool $pepStatus = false;

    public ?string $occupation = null;

    public ?string $employerName = null;

    public ?string $employerAddress = null;

    public bool $isActive = false;

    public array $idTypes = [];

    public array $nationalities = [];

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
        $this->fullName = $customer->full_name;
        $this->idType = $customer->id_type;
        $this->nationality = $customer->nationality;
        $this->address = $customer->address;
        $this->phone = $customer->phone;
        $this->email = $customer->email;
        $this->pepStatus = (bool) $customer->pep_status;
        $this->occupation = $customer->occupation;
        $this->employerName = $customer->employer_name;
        $this->employerAddress = $customer->employer_address;
        $this->isActive = $customer->is_active;
        $this->idTypes = [
            'MyKad' => 'MyKad (Malaysian IC)',
            'Passport' => 'Passport',
            'Others' => 'Other ID',
        ];
        $this->nationalities = [
            'Malaysian',
            'Singaporean',
            'Indonesian',
            'Thai',
            'Filipino',
            'Vietnamese',
            'Chinese',
            'Indian',
            'Bangladeshi',
            'Pakistani',
            'Other',
        ];
    }

    protected function rules(): array
    {
        return [
            'fullName' => 'required|string|max:255',
            'idType' => 'required|in:MyKad,Passport,Others',
            'nationality' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(\+?6?01)[0-9]{8,9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'pepStatus' => 'sometimes|boolean',
            'occupation' => 'nullable|string|max:255',
            'employerName' => 'nullable|string|max:255',
            'employerAddress' => 'nullable|string|max:500',
            'isActive' => 'boolean',
        ];
    }

    public function save(): mixed
    {
        $this->validate();

        try {
            $customerService = app(CustomerService::class);
            $customer = $customerService->updateCustomer($this->customer, [
                'full_name' => $this->fullName,
                'id_type' => $this->idType,
                'nationality' => $this->nationality,
                'address' => $this->address,
                'phone' => $this->phone,
                'email' => $this->email,
                'pep_status' => $this->pepStatus,
                'occupation' => $this->occupation,
                'employer_name' => $this->employerName,
                'employer_address' => $this->employerAddress,
                'is_active' => $this->isActive,
            ], auth()->id());

            $this->success("Customer {$customer->full_name} updated successfully!");

            return $this->redirect(route('customers.show', $customer));
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.customers.edit');
    }
}
