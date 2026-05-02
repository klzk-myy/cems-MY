<?php

namespace App\Livewire\Reports\Analytics;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Livewire\BaseComponent;
use App\Models\CurrencyPosition;
use App\Models\ExchangeRate;
use App\Models\Transaction;
use App\Services\MathService;
use Illuminate\View\View;

class Profitability extends BaseComponent
{
    public string $startDate;

    public string $endDate;

    public array $positions = [];

    public array $totals = [];

    public function mount(): void
    {
        $this->startDate = now()->subMonth()->startOfMonth()->toDateString();
        $this->endDate = now()->subMonth()->endOfMonth()->toDateString();
        $this->loadPositions();
    }

    protected function loadPositions(): void
    {
        $currencyPositions = CurrencyPosition::with('currency')->get();

        $this->positions = $currencyPositions->map(function ($position) {
            $stats = $this->calculateCurrencyProfitability($position->currency_code);

            return [
                'currency' => $position->currency,
                'currency_code' => $position->currency_code,
                'balance' => $position->balance,
                'avg_cost_rate' => $position->avg_cost_rate,
                'current_rate' => $this->getCurrentRate($position->currency_code),
                'unrealized_pnl' => $stats['unrealized_pnl'],
                'realized_pnl' => $stats['realized_pnl'],
                'total_pnl' => $stats['total_pnl'],
                'buy_volume' => $stats['buy_volume'],
                'sell_volume' => $stats['sell_volume'],
            ];
        })->toArray();

        $this->totals = [
            'total_unrealized' => array_sum(array_column($this->positions, 'unrealized_pnl')),
            'total_realized' => array_sum(array_column($this->positions, 'realized_pnl')),
            'total_pnl' => array_sum(array_column($this->positions, 'total_pnl')),
        ];
    }

    protected function calculateCurrencyProfitability(string $currencyCode): array
    {
        $mathService = app(MathService::class);
        $position = CurrencyPosition::where('currency_code', $currencyCode)->first();

        if (! $position) {
            return [
                'unrealized_pnl' => '0',
                'realized_pnl' => '0',
                'total_pnl' => '0',
                'buy_volume' => '0',
                'sell_volume' => '0',
            ];
        }

        $currentRate = $this->getCurrentRate($currencyCode);
        $avgCost = (string) $position->avg_cost_rate;
        $balance = (string) $position->balance;

        $unrealizedPnl = $mathService->multiply(
            $mathService->subtract((string) $currentRate, $avgCost),
            $balance
        );

        $sells = Transaction::where('currency_code', $currencyCode)
            ->where('type', TransactionType::Sell)
            ->where('status', TransactionStatus::Completed)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->get();

        $realizedPnl = '0';
        foreach ($sells as $sell) {
            $sellRate = (string) $sell->rate;
            $sellAmount = (string) $sell->amount_foreign;
            $gain = $mathService->multiply(
                $mathService->subtract($sellRate, $avgCost),
                $sellAmount
            );
            $realizedPnl = $mathService->add((string) $realizedPnl, $gain);
        }

        $buyVolume = Transaction::where('currency_code', $currencyCode)
            ->where('type', TransactionType::Buy)
            ->where('status', TransactionStatus::Completed)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->sum('amount_local');

        $sellVolume = Transaction::where('currency_code', $currencyCode)
            ->where('type', TransactionType::Sell)
            ->where('status', TransactionStatus::Completed)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->sum('amount_local');

        return [
            'unrealized_pnl' => $unrealizedPnl,
            'realized_pnl' => $realizedPnl,
            'total_pnl' => $mathService->add($unrealizedPnl, $realizedPnl),
            'buy_volume' => (string) ($buyVolume ?? '0'),
            'sell_volume' => (string) ($sellVolume ?? '0'),
        ];
    }

    protected function getCurrentRate(string $currencyCode): string
    {
        $rate = ExchangeRate::where('currency_code', $currencyCode)
            ->where('is_active', true)
            ->latest()
            ->first();

        return $rate ? (string) $rate->rate : '0';
    }

    public function updatedStartDate(): void
    {
        $this->loadPositions();
    }

    public function updatedEndDate(): void
    {
        $this->loadPositions();
    }

    public function render(): View
    {
        return view('livewire.reports.analytics.profitability');
    }
}
