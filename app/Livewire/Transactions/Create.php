<?php

namespace App\Livewire\Transactions;

use App\Livewire\BaseComponent;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Services\MathService;
use App\Services\RateManagementService;
use App\Services\TransactionService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Validate;

class Create extends BaseComponent
{
    // Step management
    public int $currentStep = 1;

    // Step 1: Customer selection
    public ?int $customerId = null;

    public ?string $customerName = null;

    public ?Customer $selectedCustomer = null;

    public string $customerSearch = '';

    public Collection $searchResults;

    public bool $showCustomerResults = false;

    // Step 2: Currency & Amount
    #[Validate('required')]
    public ?string $type = null;

    #[Validate('required')]
    public ?string $currencyCode = null;

    #[Validate('required|numeric|min:0.01')]
    public ?string $amountForeign = null;

    #[Validate('required|numeric|min:0.0001')]
    public ?string $rate = null;

    #[Validate('required')]
    public ?int $branchId = null;

    #[Validate('required')]
    public ?int $counterId = null;

    #[Validate('required')]
    public ?string $purpose = null;

    #[Validate('required')]
    public ?string $sourceOfFunds = null;

    // Calculated MYR value
    public ?string $amountLocal = null;

    // Available options
    public Collection $currencies;

    public Collection $branches;

    public Collection $counters;

    // Exchange rates storage
    public array $exchangeRates = [];

    public function mount(): void
    {
        $this->currencies = Currency::where('is_active', true)->get();
        $this->branches = Branch::active()->get();
        $this->loadExchangeRates();
    }

    protected function loadExchangeRates(): void
    {
        $rateService = app(RateManagementService::class);
        $rates = $rateService->getCurrentRates();

        foreach ($rates as $rate) {
            $this->exchangeRates[$rate->currency_code] = [
                'buy' => $rate->buy_rate,
                'sell' => $rate->sell_rate,
            ];
        }
    }

    // Step navigation
    public function nextStep(): void
    {
        if ($this->currentStep === 1) {
            $this->validateStep1();
        } elseif ($this->currentStep === 2) {
            $this->validateStep2();
        }

        if ($this->currentStep < 3) {
            $this->currentStep++;
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= 3) {
            $this->currentStep = $step;
        }
    }

    // Step 1: Customer search
    public function updatedCustomerSearch(): void
    {
        if (strlen($this->customerSearch) < 2) {
            $this->searchResults = collect();
            $this->showCustomerResults = false;

            return;
        }

        $this->searchResults = Customer::where('full_name', 'like', '%'.$this->customerSearch.'%')
            ->orWhere('id_number_hash', 'like', '%'.$this->customerSearch.'%')
            ->active()
            ->limit(10)
            ->get();

        $this->showCustomerResults = $this->searchResults->isNotEmpty();
    }

    public function selectCustomer(Customer $customer): void
    {
        $this->selectedCustomer = $customer;
        $this->customerId = $customer->id;
        $this->customerName = $customer->full_name;
        $this->customerSearch = $customer->full_name;
        $this->showCustomerResults = false;
    }

    public function clearCustomer(): void
    {
        $this->selectedCustomer = null;
        $this->customerId = null;
        $this->customerName = null;
        $this->customerSearch = '';
        $this->searchResults = collect();
    }

    protected function validateStep1(): void
    {
        if (! $this->customerId) {
            $this->addError('customerId', 'Please select a customer');

            return;
        }

        $this->selectedCustomer = Customer::find($this->customerId);
        if (! $this->selectedCustomer) {
            $this->addError('customerId', 'Customer not found');
        }
    }

    // Step 2: Currency & Amount
    public function updatedCurrencyCode(): void
    {
        if ($this->currencyCode && isset($this->exchangeRates[$this->currencyCode])) {
            $rates = $this->exchangeRates[$this->currencyCode];
            // Auto-fill rate based on transaction type
            if ($this->type === 'Sell') {
                $this->rate = $rates['sell'];
            } else {
                $this->rate = $rates['buy'];
            }
            $this->calculateAmountLocal();
        }
    }

    public function updatedType(): void
    {
        $this->updatedCurrencyCode();
    }

    public function updatedAmountForeign(): void
    {
        $this->calculateAmountLocal();
    }

    public function updatedRate(): void
    {
        $this->calculateAmountLocal();
    }

    protected function calculateAmountLocal(): void
    {
        if (! $this->amountForeign || ! $this->rate) {
            $this->amountLocal = null;

            return;
        }

        $mathService = app(MathService::class);
        $this->amountLocal = $mathService->multiply($this->amountForeign, $this->rate);
    }

    public function resetRateToDaily(): void
    {
        $this->updatedCurrencyCode();
    }

    protected function validateStep2(): void
    {
        $this->validate([
            'type' => 'required',
            'currencyCode' => 'required',
            'amountForeign' => 'required|numeric|min:0.01',
            'rate' => 'required|numeric|min:0.0001',
            'branchId' => 'required',
            'counterId' => 'required',
            'purpose' => 'required',
            'sourceOfFunds' => 'required',
        ]);
    }

    // Step 3: Submit
    public function submit(): mixed
    {
        $this->validate([
            'customerId' => 'required|exists:customers,id',
            'type' => 'required',
            'currencyCode' => 'required',
            'amountForeign' => 'required|numeric|min:0.01',
            'rate' => 'required|numeric|min:0.0001',
            'branchId' => 'required|exists:branches,id',
            'counterId' => 'required',
            'purpose' => 'required',
            'sourceOfFunds' => 'required',
        ]);

        try {
            $counter = Counter::find($this->counterId);
            if (! $counter) {
                $this->error('Counter not found');

                return null;
            }

            $data = [
                'customer_id' => $this->customerId,
                'type' => $this->type,
                'currency_code' => $this->currencyCode,
                'amount_foreign' => $this->amountForeign,
                'rate' => $this->rate,
                'till_id' => $counter->code,
                'purpose' => $this->purpose,
                'source_of_funds' => $this->sourceOfFunds,
            ];

            $transactionService = app(TransactionService::class);
            $transactionService->createTransaction($data);

            $this->success('Transaction created successfully!');

            return redirect()->route('transactions.index');
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.transactions.create.index');
    }
}
