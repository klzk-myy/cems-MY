<?php

namespace App\Livewire\Customers;

use App\Livewire\BaseComponent;
use App\Services\CustomerService;
use Illuminate\View\View;

class Create extends BaseComponent
{
    public string $fullName = '';

    public string $idType = 'MyKad';

    public string $idNumber = '';

    public string $dateOfBirth = '';

    public string $nationality = 'Malaysian';

    public ?string $address = null;

    public ?string $phone = null;

    public ?string $email = null;

    public bool $pepStatus = false;

    public ?string $occupation = null;

    public ?string $employerName = null;

    public ?string $employerAddress = null;

    public array $idTypes = [];

    public array $nationalities = [];

    public function mount(): void
    {
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
            'idNumber' => 'required|string|max:50',
            'dateOfBirth' => 'required|date|before:today',
            'nationality' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(\+?6?01)[0-9]{8,9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'pepStatus' => 'sometimes|boolean',
            'occupation' => 'nullable|string|max:255',
            'employerName' => 'nullable|string|max:255',
            'employerAddress' => 'nullable|string|max:500',
        ];
    }

    public function save(): mixed
    {
        $this->validate();

        try {
            $customerService = app(CustomerService::class);
            $customer = $customerService->createCustomer([
                'full_name' => $this->fullName,
                'id_type' => $this->idType,
                'id_number' => $this->idNumber,
                'date_of_birth' => $this->dateOfBirth,
                'nationality' => $this->nationality,
                'address' => $this->address,
                'phone' => $this->phone,
                'email' => $this->email,
                'pep_status' => $this->pepStatus,
                'occupation' => $this->occupation,
                'employer_name' => $this->employerName,
                'employer_address' => $this->employerAddress,
            ], auth()->id());

            $message = "Customer {$customer->full_name} created successfully.";
            if ($customer->sanction_hit) {
                $message .= ' WARNING: Sanction match(es) found - customer flagged as High Risk.';
            }
            $this->success($message);

            return $this->redirect(route('customers.show', $customer));
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.customers.create');
    }
}
