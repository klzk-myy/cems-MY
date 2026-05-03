<?php

namespace App\Livewire\Str;

use App\Livewire\BaseComponent;
use Illuminate\View\View;

class Create extends BaseComponent
{
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

    public function create(): mixed
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
            $this->success('STR created successfully.');

            return $this->redirect(route('str.index'));
        } catch (\Exception $e) {
            $this->error('Failed to create STR: '.$e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.str.create');
    }
}
