<?php

namespace App\Livewire\Stock;

use App\Enums\TransactionType;
use App\Livewire\BaseComponent;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Services\MathService;

class Reconciliation extends BaseComponent
{
    public string $tillId = '';

    public string $date = '';

    public array $reconciliation = [];

    public array $transactions = [];

    public array $tillBalance = [];

    public function mount(string $tillId = '', ?string $date = null)
    {
        $this->tillId = $tillId ?: request('till_id', '');
        $this->date = $date ?: now()->toDateString();

        if ($this->tillId) {
            $this->loadReconciliation();
        }
    }

    public function loadReconciliation(): void
    {
        if (! $this->tillId) {
            return;
        }

        $mathService = new MathService;

        // Get till balance
        $tillBalance = TillBalance::with(['currency', 'opener', 'closer'])
            ->where('till_id', $this->tillId)
            ->whereDate('date', $this->date)
            ->first();

        if (! $tillBalance) {
            return;
        }

        $this->tillBalance = [
            'id' => $tillBalance->id,
            'till_id' => $tillBalance->till_id,
            'currency_code' => $tillBalance->currency_code,
            'currency' => $tillBalance->currency ? [
                'code' => $tillBalance->currency->code,
                'name' => $tillBalance->currency->name,
            ] : null,
            'opening_balance' => $tillBalance->opening_balance,
            'closing_balance' => $tillBalance->closing_balance,
            'opener' => $tillBalance->opener ? [
                'name' => $tillBalance->opener->name,
            ] : null,
            'closer' => $tillBalance->closer ? [
                'name' => $tillBalance->closer->name,
            ] : null,
            'closed_at' => $tillBalance->closed_at,
        ];

        // Get all transactions for this till on this date
        $transactions = Transaction::with(['customer', 'currency'])
            ->where('till_id', $this->tillId)
            ->whereDate('created_at', $this->date)
            ->orderBy('created_at', 'asc')
            ->get();

        $this->transactions = $transactions->map(function ($tx) use ($mathService) {
            $myrValue = $mathService->multiply(
                (string) $tx->amount_local,
                (string) $tx->rate
            );

            return [
                'id' => $tx->id,
                'created_at' => $tx->created_at->format('H:i:s'),
                'type' => $tx->type->value,
                'amount' => $tx->amount_local,
                'currency_code' => $tx->currency_code,
                'rate' => $tx->rate,
                'myr_value' => $myrValue,
                'customer_name' => $tx->customer->name ?? 'N/A',
            ];
        })->toArray();

        // Calculate summary statistics
        $buyAmount = $this->calculateTransactionSum($transactions, TransactionType::Buy, $mathService);
        $sellAmount = $this->calculateTransactionSum($transactions, TransactionType::Sell, $mathService);
        $netFlow = $mathService->subtract($buyAmount, $sellAmount);

        // Calculate expected closing balance
        $expectedClosing = $mathService->add(
            (string) $tillBalance->opening_balance,
            (string) $netFlow
        );

        // Get actual closing balance
        $actualClosing = $tillBalance->closing_balance
            ? (string) $tillBalance->closing_balance
            : null;

        // Calculate variance
        $variance = $actualClosing !== null
            ? $mathService->subtract((string) $actualClosing, (string) $expectedClosing)
            : null;

        $this->reconciliation = [
            'opening_balance' => $tillBalance->opening_balance,
            'purchases' => [
                'count' => $transactions->where('type', TransactionType::Buy->value)->count(),
                'total' => $buyAmount,
            ],
            'sales' => [
                'count' => $transactions->where('type', TransactionType::Sell->value)->count(),
                'total' => $sellAmount,
            ],
            'expected_closing' => $expectedClosing,
            'actual_closing' => $actualClosing,
            'variance' => $variance,
            'is_closed' => $tillBalance->closed_at !== null,
            'net_flow' => $netFlow,
        ];
    }

    protected function calculateTransactionSum($transactions, TransactionType $type, MathService $mathService): string
    {
        $sum = '0';
        foreach ($transactions->where('type', $type->value) as $transaction) {
            $sum = $mathService->add($sum, (string) $transaction->amount_local);
        }

        return $sum;
    }

    public function updatedTillId(): void
    {
        $this->loadReconciliation();
    }

    public function updatedDate(): void
    {
        $this->loadReconciliation();
    }

    public function render()
    {
        return view('livewire.stock.reconciliation');
    }
}
