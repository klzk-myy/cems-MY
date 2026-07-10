<?php

namespace App\Services\Branch;

use App\Models\Counter;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Services\System\MathService;

class TillBalanceManager
{
    public function __construct(protected MathService $mathService) {}

    public function openBalance(Counter $till, string $currencyCode, ?int $openedBy = null): TillBalance
    {
        $currency = Currency::where('code', $currencyCode)->firstOrFail();

        $openedBy = $openedBy ?? auth()->id();

        if ($openedBy === null) {
            throw new \InvalidArgumentException('opened_by is required to open a till balance');
        }

        return TillBalance::firstOrCreate(
            [
                'till_id' => $till->code,
                'currency_code' => $currency->code,
                'date' => today(),
            ],
            [
                'branch_id' => $till->branch_id,
                'opened_by' => $openedBy,
                'opening_balance' => '0',
                'closing_balance' => null,
                'variance' => null,
                'foreign_total' => '0',
                'transaction_total' => '0',
                'buy_total_foreign' => '0',
                'sell_total_foreign' => '0',
            ]
        );
    }

    public function adjustBalance(TillBalance $balance, string $field, string $amount, string $operation = 'add', bool $lock = false): TillBalance
    {
        $allowedFields = [
            'opening_balance',
            'closing_balance',
            'foreign_total',
            'transaction_total',
            'buy_total_foreign',
            'sell_total_foreign',
        ];

        if (! in_array($field, $allowedFields, true)) {
            throw new \InvalidArgumentException("Invalid till balance field: {$field}");
        }

        if ($lock) {
            $balance = TillBalance::where('id', $balance->id)->lockForUpdate()->firstOrFail();
        }

        $current = $balance->{$field};
        $currentString = $current === null ? '0' : (string) $current;

        $newValue = match ($operation) {
            'add' => $this->mathService->add($currentString, $amount),
            'subtract' => $this->mathService->subtract($currentString, $amount),
            default => throw new \InvalidArgumentException("Unknown till balance operation: {$operation}"),
        };

        $balance->update([$field => $newValue]);

        return $balance->refresh();
    }

    public function currentBalance(Counter $till, string $currencyCode, bool $lock = false): ?TillBalance
    {
        $query = TillBalance::where('till_id', $till->code)
            ->where('currency_code', $currencyCode)
            ->whereDate('date', today())
            ->whereNull('closed_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function variance(TillBalance $balance): string
    {
        return $balance->calculateVariance();
    }
}
