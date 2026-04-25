<?php

namespace App\Livewire\Stock;

use App\Enums\TransactionType;
use App\Livewire\BaseComponent;
use App\Models\CurrencyPosition;
use App\Models\Transaction;
use App\Services\MathService;

class Position extends BaseComponent
{
    public ?CurrencyPosition $position = null;

    public array $positionData = [];

    public array $transactions = [];

    public int $positionId;

    public function mount(int $positionId)
    {
        $this->positionId = $positionId;
        $this->loadPosition();
    }

    public function loadPosition(): void
    {
        $this->position = CurrencyPosition::with('currency')->find($this->positionId);

        if (! $this->position) {
            return;
        }

        $mathService = new MathService;

        // Calculate market value
        $marketValue = $mathService->multiply(
            $this->position->balance,
            $this->position->last_valuation_rate ?? '0'
        );

        $this->positionData = [
            'id' => $this->position->id,
            'currency_code' => $this->position->currency_code,
            'currency_name' => $this->position->currency->name ?? $this->position->currency_code,
            'quantity' => $this->position->balance,
            'avg_cost' => $this->position->avg_cost_rate ?? '0',
            'market_value' => $marketValue,
            'unrealized_pl' => $this->position->unrealized_pnl ?? '0',
            'last_valuation_rate' => $this->position->last_valuation_rate ?? '0',
            'last_valuation_at' => $this->position->last_valuation_at,
            'till_id' => $this->position->till_id,
        ];

        // Load recent transactions for this currency
        $this->transactions = Transaction::where('currency_code', $this->position->currency_code)
            ->where('type', TransactionType::Buy->value)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($tx) use ($mathService) {
                $myrValue = $mathService->multiply(
                    (string) $tx->amount_local,
                    (string) $tx->rate
                );

                return [
                    'id' => $tx->id,
                    'created_at' => $tx->created_at->format('Y-m-d H:i'),
                    'type' => $tx->type->value,
                    'amount' => $tx->amount_local,
                    'rate' => $tx->rate,
                    'myr_value' => $myrValue,
                    'customer_name' => $tx->customer->name ?? 'N/A',
                ];
            })
            ->toArray();
    }

    public function render()
    {
        return view('livewire.stock.position');
    }
}
