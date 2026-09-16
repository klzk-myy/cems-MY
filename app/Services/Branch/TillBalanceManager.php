<?php

namespace App\Services\Branch;

use App\Enums\TransactionType;
use App\Exceptions\Domain\NegativeTillCloseException;
use App\Exceptions\Domain\TillAlreadyOpenException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Exceptions\Domain\TillClosedException;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Services\System\MathService;
use App\Support\ActorContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TillBalanceManager
{
    public function __construct(
        protected MathService $mathService,
        protected TillService $tillService,
    ) {}

    private function resolveCounter(string|int $tillIdentifier): ?Counter
    {
        return Counter::findByCodeOrId($tillIdentifier);
    }

    private function resolveOpenedBy(?int $openedBy, string $currencyCode, string $tillId): int
    {
        $openedBy = $openedBy ?? ActorContext::capture()->userId;

        if ($openedBy === null) {
            throw new TillBalanceMissingException($currencyCode, $tillId);
        }

        return (int) $openedBy;
    }

    public function openTill(
        Counter $till,
        string $currencyCode,
        string $openingBalance,
        ?int $openedBy = null,
        ?string $notes = null
    ): TillBalance {
        $currency = Currency::where('code', $currencyCode)->firstOrFail();
        $openedBy = $this->resolveOpenedBy($openedBy, $currency->code, $till->code);

        // The (till_id, date, currency_code) unique index was dropped, so the
        // duplicate check below can race with a concurrent openTill. Lock the
        // counter row as a stable serialization anchor for the whole open.
        return DB::transaction(function () use ($till, $currency, $openingBalance, $openedBy, $notes) {
            Counter::whereKey($till->id)->lockForUpdate()->firstOrFail();

            $existing = TillBalance::where('till_id', $till->code)
                ->where('currency_code', $currency->code)
                ->whereDate('date', today())
                ->first();

            if ($existing) {
                throw new TillAlreadyOpenException($till->code);
            }

            return TillBalance::openFor(
                $till->code,
                $currency->code,
                $till->branch_id,
                $openingBalance,
                today(),
                $openedBy,
                ['notes' => $notes]
            );
        });
    }

    public function closeTill(
        TillBalance $tillBalance,
        string $closingBalance,
        ?int $closedBy = null,
        ?string $notes = null
    ): TillBalance {
        if ($tillBalance->closed_at) {
            throw new TillClosedException($tillBalance->till_id);
        }

        $counter = $this->resolveCounter($tillBalance->till_id);

        if (! $counter) {
            Log::warning('Counter not found for till balance', [
                'till_id' => $tillBalance->till_id,
                'currency_code' => $tillBalance->currency_code,
            ]);

            throw new TillBalanceMissingException($tillBalance->currency_code, $tillBalance->till_id);
        }

        $closedBy = $closedBy ?? ActorContext::capture()->userId;

        if ($closedBy === null) {
            throw new TillBalanceMissingException($tillBalance->currency_code, $tillBalance->till_id);
        }

        $netFlow = $this->tillService->calculateNetFlow($tillBalance->till_id, $tillBalance->currency_code);

        $expectedClosing = $this->mathService->add(
            (string) $tillBalance->opening_balance,
            (string) $netFlow
        );
        $variance = $this->mathService->subtract($closingBalance, $expectedClosing);

        // A till cannot physically hold a negative amount of cash: reject
        // negative closings outright unless an explicit tolerance is configured
        // (config cems.till_negative_tolerance, e.g. for sealed-book handover).
        if ($this->mathService->compare($closingBalance, '0') < 0) {
            $tolerance = (string) config('cems.till_negative_tolerance', '0');

            if ($this->mathService->compare(
                $this->mathService->abs($closingBalance),
                $tolerance
            ) > 0) {
                throw new NegativeTillCloseException($tillBalance->currency_code, $tillBalance->till_id);
            }
        }

        $tillBalance->update([
            'closing_balance' => $closingBalance,
            'variance' => $variance,
            'closed_by' => $closedBy,
            'closed_at' => now(),
            'notes' => $notes,
        ]);

        return $tillBalance->refresh();
    }

    public function openBalance(Counter $till, string $currencyCode, ?int $openedBy = null): TillBalance
    {
        $currency = Currency::where('code', $currencyCode)->firstOrFail();

        $openedBy = $this->resolveOpenedBy($openedBy, $currency->code, $till->code);

        // The (till_id, date, currency_code) unique index was dropped by
        // migration 2026_04_16_060922, so firstOrCreate() could race into
        // duplicate open rows. Lock candidate rows inside a transaction and
        // create only when no open row exists yet.
        return DB::transaction(function () use ($till, $currency, $openedBy) {
            // Serialize on the counter row: when no open row exists yet, the
            // lockForUpdate below locks nothing and two callers could both
            // insert.
            Counter::whereKey($till->id)->lockForUpdate()->firstOrFail();

            $existing = TillBalance::where('till_id', $till->code)
                ->where('currency_code', $currency->code)
                ->whereDate('date', today())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return TillBalance::openFor(
                $till->code,
                $currency->code,
                $till->branch_id,
                '0',
                today(),
                $openedBy
            );
        });
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
            throw new TillBalanceMissingException($balance->currency_code, $balance->till_id);
        }

        if ($lock) {
            $balance = TillBalance::where('id', $balance->id)->lockForUpdate()->firstOrFail();
        }

        $current = $balance->{$field};
        $currentString = $current === null ? '0' : (string) $current;

        $newValue = match ($operation) {
            'add' => $this->mathService->add($currentString, $amount),
            'subtract' => $this->mathService->subtract($currentString, $amount),
            default => throw new TillBalanceMissingException($balance->currency_code, $balance->till_id),
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
        // All adjustments happen exactly once, inside a single DB transaction.
        // Rows are read with lockForUpdate() inside that transaction so the row
        // locks are held until commit and concurrent bookings serialize.
        DB::transaction(function () use ($tillBalance, $type, $amountLocal, $amountForeign, $lock) {
            $counter = $this->resolveCounter($tillBalance->till_id);

            if (! $counter) {
                throw new TillBalanceMissingException($tillBalance->currency_code, $tillBalance->till_id);
            }

            $foreignBalance = $this->currentBalance($counter, $tillBalance->currency_code, $lock);
            if (! $foreignBalance) {
                throw new TillBalanceMissingException($tillBalance->currency_code, $tillBalance->till_id);
            }

            $myrBalance = $this->currentBalance($counter, Currency::baseCurrency(), $lock);
            if (! $myrBalance) {
                throw new TillBalanceMissingException(Currency::baseCurrency(), $tillBalance->till_id);
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
        });
    }

    public function reverseTransaction(
        TillBalance $tillBalance,
        TransactionType $type,
        string $amountLocal,
        string $amountForeign,
        bool $lock = true
    ): void {
        // A single transaction holds the locked reads and applies exactly one
        // set of adjustments covering both the FX and MYR legs. Missing counter
        // or till balances throw so callers know the books were not corrected.
        DB::transaction(function () use ($tillBalance, $type, $amountLocal, $amountForeign, $lock) {
            $counter = $this->resolveCounter($tillBalance->till_id);

            if (! $counter) {
                Log::warning('No counter found for reversal', [
                    'till_id' => $tillBalance->till_id,
                    'currency_code' => $tillBalance->currency_code,
                ]);

                throw new TillBalanceMissingException($tillBalance->currency_code, $tillBalance->till_id);
            }

            $foreignBalance = $this->currentBalance($counter, $tillBalance->currency_code, $lock);
            if (! $foreignBalance) {
                Log::warning('No open till balance found for reversal', [
                    'till_id' => $tillBalance->till_id,
                    'currency_code' => $tillBalance->currency_code,
                ]);

                throw new TillBalanceMissingException($tillBalance->currency_code, $tillBalance->till_id);
            }

            $myrBalance = $this->currentBalance($counter, Currency::baseCurrency(), $lock);

            if (! $myrBalance) {
                Log::warning('No open MYR till balance found for reversal', [
                    'till_id' => $tillBalance->till_id,
                ]);

                throw new TillBalanceMissingException(Currency::baseCurrency(), $tillBalance->till_id);
            }

            if ($type === TransactionType::Buy) {
                $this->adjustBalance($foreignBalance, 'foreign_total', $amountForeign, 'subtract', false);
                $this->adjustBalance($foreignBalance, 'buy_total_foreign', $amountForeign, 'subtract', false);
            } else {
                $this->adjustBalance($foreignBalance, 'foreign_total', $amountForeign, 'add', false);
                $this->adjustBalance($foreignBalance, 'sell_total_foreign', $amountForeign, 'subtract', false);
            }

            $myrOperation = $type === TransactionType::Buy ? 'add' : 'subtract';

            $this->adjustBalance($myrBalance, 'transaction_total', $amountLocal, $myrOperation, false);
        });
    }
}
