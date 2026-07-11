<?php

namespace App\Services\Branch;

use App\Enums\TransactionType;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Services\System\MathService;
use Illuminate\Support\Facades\Log;

class TillBalanceManager
{
    public function __construct(
        protected MathService $mathService,
        protected TillService $tillService,
    ) {}

    public function openTill(
        Counter $till,
        string $currencyCode,
        string $openingBalance,
        ?int $openedBy = null,
        ?string $notes = null
    ): TillBalance {
        $currency = Currency::where('code', $currencyCode)->firstOrFail();
        $openedBy = $openedBy ?? auth()->id();

        if ($openedBy === null) {
            throw new \InvalidArgumentException('opened_by is required to open a till balance');
        }

        $existing = TillBalance::where('till_id', $till->code)
            ->where('currency_code', $currency->code)
            ->whereDate('date', today())
            ->first();

        if ($existing) {
            throw new \RuntimeException('Till already opened for this currency today.');
        }

        return TillBalance::create([
            'till_id' => $till->code,
            'currency_code' => $currency->code,
            'branch_id' => $till->branch_id,
            'opening_balance' => $openingBalance,
            'closing_balance' => null,
            'variance' => null,
            'foreign_total' => '0',
            'transaction_total' => '0',
            'buy_total_foreign' => '0',
            'sell_total_foreign' => '0',
            'date' => today(),
            'opened_by' => $openedBy,
            'notes' => $notes,
        ]);
    }

    public function closeTill(
        TillBalance $tillBalance,
        string $closingBalance,
        ?int $closedBy = null,
        ?string $notes = null
    ): TillBalance {
        if ($tillBalance->closed_at) {
            throw new \RuntimeException('Till already closed for today.');
        }

        $counter = Counter::where('code', $tillBalance->till_id)
            ->orWhere('id', $tillBalance->till_id)
            ->first();

        $netFlow = $counter
            ? $this->tillService->calculateNetFlow($tillBalance->till_id, $tillBalance->currency_code)
            : '0';

        $expectedClosing = $this->mathService->add(
            (string) $tillBalance->opening_balance,
            (string) $netFlow
        );
        $variance = $this->mathService->subtract($closingBalance, $expectedClosing);

        $tillBalance->update([
            'closing_balance' => $closingBalance,
            'variance' => $variance,
            'closed_by' => $closedBy ?? auth()->id(),
            'closed_at' => now(),
            'notes' => $notes,
        ]);

        return $tillBalance->refresh();
    }

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

    public function applyTransaction(
        TillBalance $tillBalance,
        TransactionType $type,
        string $amountLocal,
        string $amountForeign,
        bool $lock = true
    ): void {
        $counter = Counter::where('code', $tillBalance->till_id)
            ->orWhere('id', $tillBalance->till_id)
            ->first();

        if (! $counter) {
            throw new TillBalanceMissingException($tillBalance->currency_code, $tillBalance->till_id);
        }

        $foreignBalance = $this->currentBalance($counter, $tillBalance->currency_code, $lock);
        if (! $foreignBalance) {
            throw new TillBalanceMissingException($tillBalance->currency_code, $tillBalance->till_id);
        }

        $myrBalance = $this->currentBalance($counter, 'MYR', $lock);
        if (! $myrBalance) {
            throw new TillBalanceMissingException('MYR', $tillBalance->till_id);
        }

        if ($type === TransactionType::Buy) {
            $this->adjustBalance($foreignBalance, 'buy_total_foreign', $amountForeign, 'add', false);
            $this->adjustBalance($foreignBalance, 'foreign_total', $amountForeign, 'add', false);
        } else {
            $this->adjustBalance($foreignBalance, 'sell_total_foreign', $amountForeign, 'add', false);
            $this->adjustBalance($foreignBalance, 'foreign_total', $amountForeign, 'subtract', false);
        }

        $myrOperation = $type === TransactionType::Buy ? 'subtract' : 'add';
        $this->adjustBalance($myrBalance, 'transaction_total', $amountLocal, $myrOperation, false);
    }

    public function reverseTransaction(
        TillBalance $tillBalance,
        TransactionType $type,
        string $amountLocal,
        string $amountForeign,
        bool $lock = true
    ): void {
        $counter = Counter::where('code', $tillBalance->till_id)
            ->orWhere('id', $tillBalance->till_id)
            ->first();

        if (! $counter) {
            Log::warning('No counter found for reversal', [
                'till_id' => $tillBalance->till_id,
                'currency_code' => $tillBalance->currency_code,
            ]);

            return;
        }

        $foreignBalance = $this->currentBalance($counter, $tillBalance->currency_code, $lock);
        if (! $foreignBalance) {
            Log::warning('No open till balance found for reversal', [
                'till_id' => $tillBalance->till_id,
                'currency_code' => $tillBalance->currency_code,
            ]);

            return;
        }

        if ($type === TransactionType::Buy) {
            $this->adjustBalance($foreignBalance, 'foreign_total', $amountForeign, 'subtract', false);
            $this->adjustBalance($foreignBalance, 'buy_total_foreign', $amountForeign, 'subtract', false);
        } else {
            $this->adjustBalance($foreignBalance, 'foreign_total', $amountForeign, 'add', false);
            $this->adjustBalance($foreignBalance, 'sell_total_foreign', $amountForeign, 'subtract', false);
        }

        $myrBalance = $this->currentBalance($counter, 'MYR', $lock);
        if ($myrBalance) {
            $myrOperation = $type === TransactionType::Buy ? 'add' : 'subtract';
            $this->adjustBalance($myrBalance, 'transaction_total', $amountLocal, $myrOperation, false);
        }
    }
}
