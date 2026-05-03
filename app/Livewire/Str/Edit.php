<?php

namespace App\Livewire\Str;

use App\Livewire\BaseComponent;
use App\Models\Str;
use Illuminate\View\View;

class Edit extends BaseComponent
{
    public ?Str $str = null;

    public ?string $transactionDate = null;

    public ?float $amount = null;

    public string $currency = 'MYR';

    public string $transactionType = 'exchange';

    public ?string $customerName = null;

    public ?string $customerIc = null;

    public ?string $customerAddress = null;

    public ?string $description = null;

    public ?string $reasons = null;

    public string $riskRating = 'medium';

    public function mount(Str $str): void
    {
        $this->str = $str;
        $this->transactionDate = $str->transaction_date?->format('Y-m-d');
        $this->amount = $str->amount;
        $this->currency = $str->currency ?? 'MYR';
        $this->transactionType = $str->transaction_type ?? 'exchange';
        $this->customerName = $str->customer_name;
        $this->customerIc = $str->customer_ic;
        $this->customerAddress = $str->customer_address;
        $this->description = $str->description;
        $this->reasons = $str->reasons;
        $this->riskRating = $str->risk_rating ?? 'medium';
    }

    public function update(): mixed
    {
        $this->validate([
            'transactionDate' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string',
            'transactionType' => 'required|string',
            'customerName' => 'required|string|max:255',
            'customerIc' => 'nullable|string|max:50',
            'customerAddress' => 'nullable|string|max:500',
            'description' => 'required|string',
            'reasons' => 'required|string',
            'riskRating' => 'required|in:low,medium,high,critical',
        ]);

        try {
            $this->str->update([
                'transaction_date' => $this->transactionDate,
                'amount' => $this->amount,
                'currency' => $this->currency,
                'transaction_type' => $this->transactionType,
                'customer_name' => $this->customerName,
                'customer_ic' => $this->customerIc,
                'customer_address' => $this->customerAddress,
                'description' => $this->description,
                'reasons' => $this->reasons,
                'risk_rating' => $this->riskRating,
            ]);

            $this->success('STR updated successfully.');

            return $this->redirect(route('str.show', $this->str));
        } catch (\Exception $e) {
            $this->error('Failed to update STR: '.$e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.str.edit');
    }
}
