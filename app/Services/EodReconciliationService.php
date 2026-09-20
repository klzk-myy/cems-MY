<?php

namespace App\Services;

use App\Enums\CounterSessionStatus;
use App\Enums\FlagStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterHandover;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\FlaggedTransaction;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Support\ActorContext;
use App\Support\BcmathHelper;
use App\ValueObjects\EodCounterReconciliationData;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * EOD Reconciliation Service
 *
 * Generates End-of-Day reconciliation reports for counter management.
 * Provides daily summaries, per-counter reconciliation, variance analysis,
 * and formal PDF reports for MSB compliance.
 */
class EodReconciliationService
{
    public function __construct(
        protected ThresholdService $thresholdService
    ) {}

    /**
     * Generate daily reconciliation summary for all counters.
     *
     * @param  Carbon  $date  Reconciliation date
     * @param  int|null  $branchId  Optional branch filter
     * @return array Daily reconciliation summary
     */
    public function generateDailyReconciliationSummary(Carbon $date, ?int $branchId = null): array
    {
        $sessions = CounterSession::with(['counter', 'user', 'handovers', 'openedByUser', 'closedByUser'])
            ->where('session_date', $date->toDateString())
            ->when($branchId, function ($query) use ($branchId) {
                $query->whereHas('counter', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                });
            })
            ->orderBy('id')
            ->get();

        // A counter can hold several sessions per day (handover opens a
        // successor); keep the first — matching the previous ->first()
        // semantics — while stats below still count sessions, not counters.
        $sessionByCounter = $sessions->groupBy('counter_id')->map->first();

        $counters = Counter::with('branch')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->active()
            ->get();

        // Batch-load everything the per-counter reconciliation needs up front
        // so the loop below is pure computation instead of ~15 queries per
        // counter.
        $counterCodes = $counters->pluck('code');
        $counterIds = $counters->pluck('id');

        $tillBalancesByTill = TillBalance::with('currency')
            ->whereIn('till_id', $counterCodes)
            ->where('date', $date->toDateString())
            ->get()
            ->groupBy('till_id');

        $transactionsByTill = Transaction::with(['customer', 'user', 'flags'])
            ->whereIn('till_id', $counterCodes)
            ->forDateRange($date->toDateString(), $date->toDateString())
            ->notCancelled()
            ->whereNotIn('status', [TransactionStatus::Failed->value, TransactionStatus::Pending->value])
            ->get()
            ->groupBy('till_id');

        $flaggedByTill = FlaggedTransaction::with(['transaction', 'transaction.customer'])
            ->whereHas('transaction', function ($query) use ($date, $counterCodes) {
                $query->whereIn('till_id', $counterCodes)
                    ->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
            })
            ->whereNotIn('status', FlagStatus::terminalValues())
            ->get()
            ->groupBy(fn (FlaggedTransaction $flag) => (string) $flag->transaction?->till_id);

        $handoversByCounter = CounterHandover::with(['counterSession', 'fromUser', 'toUser', 'supervisor'])
            ->whereHas('counterSession', function ($query) use ($date, $counterIds) {
                $query->whereIn('counter_id', $counterIds)
                    ->where('session_date', $date->toDateString());
            })
            ->orderBy('handover_time', 'asc')
            ->get()
            ->groupBy(fn ($handover) => $handover->counterSession?->counter_id);

        $counterSummaries = [];
        $totalOpeningFloat = '0';
        $totalCashReceived = '0';
        $totalCashPaidOut = '0';
        $totalClosingExpected = '0';
        $totalClosingActual = '0';
        $totalVariance = '0';

        foreach ($counters as $counter) {
            $session = $sessionByCounter->get($counter->id);

            if (! $session) {
                continue;
            }

            $summary = $this->counterReconciliationFromData(new EodCounterReconciliationData(
                counter: $counter,
                date: $date,
                session: $session,
                tillBalances: $tillBalancesByTill->get($counter->code, collect()),
                transactions: $transactionsByTill->get($counter->code, collect()),
                flaggedTransactions: $flaggedByTill->get($counter->code, collect()),
                handovers: $handoversByCounter->get($counter->id, collect()),
            ));
            $counterSummaries[] = $summary;

            $totalOpeningFloat = BcmathHelper::add($totalOpeningFloat, $summary['opening_float']);
            $totalCashReceived = BcmathHelper::add($totalCashReceived, $summary['total_cash_received']);
            $totalCashPaidOut = BcmathHelper::add($totalCashPaidOut, $summary['total_cash_paid_out']);
            $totalClosingExpected = BcmathHelper::add($totalClosingExpected, $summary['closing_float_expected']);
            $totalClosingActual = BcmathHelper::add($totalClosingActual, $summary['closing_float_actual'] ?? '0');
            $totalVariance = BcmathHelper::add($totalVariance, $summary['variance'] ?? '0');
        }

        // Get branch-level stats
        $transactions = Transaction::with(['customer', 'user', 'flags'])
            ->forDateRange($date->toDateString(), $date->toDateString())
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->get();

        $largeTransactions = $transactions->filter(function ($tx) {
            return BcmathHelper::gte((string) $tx->amount_myr, $this->thresholdService->getLargeTransactionThreshold());
        });

        $flaggedTransactions = FlaggedTransaction::with(['transaction', 'transaction.customer'])
            ->whereHas('transaction', function ($query) use ($date, $branchId) {
                $query->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
                if ($branchId) {
                    $query->where('branch_id', $branchId);
                }
            })
            ->whereNotIn('status', FlagStatus::terminalValues())
            ->get();

        return [
            'date' => $date->toDateString(),
            'branch_id' => $branchId,
            'branch_name' => $branchId ? Branch::find($branchId)->name : 'All Branches',
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total_counters' => $counters->count(),
                'active_counters' => $sessions->where('status', CounterSessionStatus::Open->value)->count(),
                'closed_counters' => $sessions->where('status', CounterSessionStatus::Closed->value)->count(),
                'handed_over_counters' => $sessions->where('status', CounterSessionStatus::HandedOver->value)->count(),
            ],
            'totals' => [
                'opening_float' => $totalOpeningFloat,
                'cash_received' => $totalCashReceived,
                'cash_paid_out' => $totalCashPaidOut,
                'closing_expected' => $totalClosingExpected,
                'closing_actual' => $totalClosingActual,
                'variance' => $totalVariance,
            ],
            'counter_summaries' => $counterSummaries,
            'large_transactions' => [
                'count' => $largeTransactions->count(),
                'total_amount_myr' => $this->sumDecimalColumn($largeTransactions, 'amount_myr'),
                'transactions' => $largeTransactions->take(50)->values(),
            ],
            'flagged_transactions' => [
                'count' => $flaggedTransactions->count(),
                'transactions' => $flaggedTransactions->take(50)->values(),
            ],
        ];
    }

    /**
     * Build the base query for reconcilable transactions for a counter on a date.
     *
     * @return Builder<Transaction>
     */
    private function reconcilableTransactionsQuery(int $counterId, Carbon $date): Builder
    {
        $counter = Counter::findOrFail($counterId);

        return Transaction::where('till_id', $counter->code)
            ->forDateRange($date->toDateString(), $date->toDateString())
            ->notCancelled()
            ->whereNotIn('status', [TransactionStatus::Failed->value, TransactionStatus::Pending->value]);
    }

    /**
     * Generate per-counter reconciliation details.
     *
     * @param  int  $counterId  Counter ID
     * @param  Carbon  $date  Reconciliation date
     * @return array Counter reconciliation details
     */
    public function generateCounterReconciliation(int $counterId, Carbon $date): array
    {
        $counter = Counter::with('branch')->findOrFail($counterId);

        $session = CounterSession::with(['user', 'openedByUser', 'closedByUser', 'handovers'])
            ->where('counter_id', $counterId)
            ->where('session_date', $date->toDateString())
            ->first();

        if (! $session) {
            return [
                'counter_id' => $counterId,
                'counter_code' => $counter->code,
                'counter_name' => $counter->name,
                'branch_name' => $counter->branch->name,
                'date' => $date->toDateString(),
                'has_session' => false,
                'message' => 'No session found for this counter on this date',
            ];
        }

        $tillBalances = TillBalance::with('currency')
            ->where('till_id', $counter->code)
            ->where('date', $date->toDateString())
            ->get();

        $transactions = $this->reconcilableTransactionsQuery($counterId, $date)
            ->with(['customer', 'user', 'flags'])
            ->get();

        $flaggedTransactions = FlaggedTransaction::with(['transaction', 'transaction.customer'])
            ->whereHas('transaction', function ($query) use ($counter, $date) {
                $query->where('till_id', $counter->code)
                    ->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
            })
            ->whereNotIn('status', FlagStatus::terminalValues())
            ->get();

        $handovers = CounterHandover::with(['counterSession', 'fromUser', 'toUser', 'supervisor'])
            ->whereHas('counterSession', function ($query) use ($counterId, $date) {
                $query->where('counter_id', $counterId)
                    ->where('session_date', $date->toDateString());
            })
            ->orderBy('handover_time', 'asc')
            ->get();

        return $this->counterReconciliationFromData(new EodCounterReconciliationData(
            counter: $counter,
            date: $date,
            session: $session,
            tillBalances: $tillBalances,
            transactions: $transactions,
            flaggedTransactions: $flaggedTransactions,
            handovers: $handovers,
        ));
    }

    /**
     * Assemble the per-counter reconciliation payload from preloaded
     * collections. Shared by the single-counter API path and the daily
     * summary batch path so both return identical shapes.
     *
     * @return array<string, mixed>
     */
    private function counterReconciliationFromData(EodCounterReconciliationData $data): array
    {
        $cashTotals = $this->cashTotalsFromTransactions($data->transactions);

        return [
            'counter_id' => $data->counter->id,
            'counter_code' => $data->counter->code,
            'counter_name' => $data->counter->name,
            'branch_name' => $data->counter->branch->name,
            'date' => $data->date->toDateString(),
            'has_session' => true,
            'session' => $this->sessionSection($data->session),
            ...$this->floatSectionFromBalances($data->tillBalances, $cashTotals),
            'variance' => $this->varianceFromBalances($data->tillBalances, $cashTotals),
            'currency_breakdown' => $this->currencyBreakdownFromBalances($data->tillBalances),
            'transactions' => $this->transactionSection($data->transactions, $cashTotals),
            'large_transactions' => $this->largeTransactionsSection($data->transactions),
            'flagged_transactions' => [
                'count' => $data->flaggedTransactions->count(),
                'transactions' => $data->flaggedTransactions->take(50)->values(),
            ],
            'handover_history' => $this->mapHandovers($data->handovers),
        ];
    }

    /**
     * MYR cash received and paid out for a counter-day. Sell-type = bureau
     * sells foreign currency, customer pays MYR (received). Buy-type = bureau
     * buys foreign currency, pays MYR (paid out).
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return array{received: string, paid_out: string}
     */
    private function cashTotalsFromTransactions(Collection $transactions): array
    {
        return [
            'received' => $this->sumDecimalColumn(
                $transactions->filter(fn ($tx) => $tx->type === TransactionType::Sell),
                'amount_myr'
            ),
            'paid_out' => $this->sumDecimalColumn(
                $transactions->filter(fn ($tx) => $tx->type === TransactionType::Buy),
                'amount_myr'
            ),
        ];
    }

    /**
     * @return array{id: int, status: string, opened_at: ?string, closed_at: ?string, opened_by: ?array{id: int, name: string}, closed_by: ?array{id: int, name: string}, current_user: ?array{id: int, name: string}}
     */
    private function sessionSection(CounterSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status->value,
            'opened_at' => $session->opened_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'opened_by' => $session->openedByUser ? [
                'id' => $session->openedByUser->id,
                'name' => $session->openedByUser->username,
            ] : null,
            'closed_by' => $session->closedByUser ? [
                'id' => $session->closedByUser->id,
                'name' => $session->closedByUser->username,
            ] : null,
            'current_user' => $session->user ? [
                'id' => $session->user->id,
                'name' => $session->user->username,
            ] : null,
        ];
    }

    /**
     * @param  Collection<int, TillBalance>  $tillBalances
     * @param  array{received: string, paid_out: string}  $cashTotals
     * @return array{opening_float: string, total_cash_received: string, total_cash_paid_out: string, closing_float_expected: string, closing_float_actual: ?string}
     */
    private function floatSectionFromBalances(Collection $tillBalances, array $cashTotals): array
    {
        // The MYR float is tracked on the MYR till row only — summing every
        // currency row would mix USD/EUR/etc. units into an MYR figure.
        $myrTillBalances = $tillBalances->where('currency_code', Currency::baseCurrency());
        $openingFloat = $this->sumDecimalColumn($myrTillBalances, 'opening_balance');

        // Expected closing = opening + received - paid out
        $closingFloatExpected = BcmathHelper::subtract(
            BcmathHelper::add($openingFloat, $cashTotals['received']),
            $cashTotals['paid_out']
        );

        // Actual MYR closing from session close
        $closingFloatActual = $this->sumDecimalColumn(
            $myrTillBalances->whereNotNull('closing_balance'),
            'closing_balance'
        );

        return [
            'opening_float' => $openingFloat,
            'total_cash_received' => $cashTotals['received'],
            'total_cash_paid_out' => $cashTotals['paid_out'],
            'closing_float_expected' => $closingFloatExpected,
            'closing_float_actual' => BcmathHelper::eq($closingFloatActual, '0') ? null : $closingFloatActual,
        ];
    }

    /**
     * Buy count covers Buy-type transactions (paid out), sell count Sell-type
     * (received). buy_total/sell_total carry the cash-flow totals — the
     * pairing is a preserved legacy quirk of the response shape.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @param  array{received: string, paid_out: string}  $cashTotals
     * @return array{total_count: int, buy_count: int, sell_count: int, buy_total: string, sell_total: string}
     */
    private function transactionSection(Collection $transactions, array $cashTotals): array
    {
        $buyTransactions = $transactions->filter(fn ($tx) => $tx->type->value === TransactionType::Buy->value);
        $sellTransactions = $transactions->filter(fn ($tx) => $tx->type->value === TransactionType::Sell->value);

        return [
            'total_count' => $transactions->count(),
            'buy_count' => $buyTransactions->count(),
            'sell_count' => $sellTransactions->count(),
            'buy_total' => $cashTotals['received'],
            'sell_total' => $cashTotals['paid_out'],
        ];
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array{count: int, total_amount_myr: string, transactions: Collection<int, Transaction>}
     */
    private function largeTransactionsSection(Collection $transactions): array
    {
        // Large transactions (> RM 10k)
        $largeTransactions = $transactions->filter(function ($tx) {
            return BcmathHelper::gte((string) $tx->amount_myr, $this->thresholdService->getLargeTransactionThreshold());
        });

        return [
            'count' => $largeTransactions->count(),
            'total_amount_myr' => $this->sumDecimalColumn($largeTransactions, 'amount_myr'),
            'transactions' => $largeTransactions->take(50)->values(),
        ];
    }

    /**
     * Calculate variance from preloaded till balances and cash totals.
     *
     * @param  Collection<int, TillBalance>  $tillBalances
     * @param  array{received: string, paid_out: string}  $cashTotals
     */
    private function varianceFromBalances(Collection $tillBalances, array $cashTotals): string
    {
        $myrTillBalances = $tillBalances->where('currency_code', Currency::baseCurrency());
        $openingFloat = $this->sumDecimalColumn($myrTillBalances, 'opening_balance');

        $expectedClosing = BcmathHelper::subtract(
            BcmathHelper::add($openingFloat, $cashTotals['received']),
            $cashTotals['paid_out']
        );

        $closedBalances = $myrTillBalances->whereNotNull('closing_balance');

        if ($closedBalances->isEmpty()) {
            // Session not yet closed, return expected closing to show pending variance
            return $expectedClosing;
        }

        return BcmathHelper::subtract(
            $this->sumDecimalColumn($closedBalances, 'closing_balance'),
            $expectedClosing
        );
    }

    /**
     * @param  Collection<int, CounterHandover>  $handovers
     * @return Collection<int, array<string, mixed>>
     */
    private function mapHandovers(Collection $handovers): Collection
    {
        /** @var Collection<int, array<string, mixed>> */
        return $handovers->map(fn ($h) => [
            'id' => $h->id,
            'from_user' => $h->fromUser ? ['id' => $h->fromUser->id, 'name' => $h->fromUser->username] : null,
            'to_user' => $h->toUser ? ['id' => $h->toUser->id, 'name' => $h->toUser->username] : null,
            'supervisor' => $h->supervisor ? ['id' => $h->supervisor->id, 'name' => $h->supervisor->username] : null,
            'handover_time' => $h->handover_time?->toIso8601String(),
            'variance_myr' => $h->variance_myr,
            'physical_count_verified' => $h->physical_count_verified,
        ]);
    }

    /**
     * Calculate variance between expected and actual closing float.
     *
     * @param  int  $counterId  Counter ID
     * @param  Carbon  $date  Reconciliation date
     * @return string Variance amount (can be negative)
     */
    public function calculateVariance(int $counterId, Carbon $date): string
    {
        $counter = Counter::findOrFail($counterId);

        $tillBalances = TillBalance::where('till_id', $counter->code)
            ->where('date', $date->toDateString())
            ->get();

        // MYR float only — see generateCounterReconciliation for why the
        // per-currency rows must not be summed into an MYR figure.
        $myrTillBalances = $tillBalances->where('currency_code', Currency::baseCurrency());
        $openingFloat = $this->sumDecimalColumn($myrTillBalances, 'opening_balance');

        // Get transactions
        $baseQuery = $this->reconcilableTransactionsQuery($counterId, $date);

        // MYR in on Sell, MYR out on Buy (same convention as the reconciliation).
        $cashReceived = (string) ((clone $baseQuery)->sell()->sum('amount_myr'));
        $cashPaidOut = (string) ((clone $baseQuery)->buy()->sum('amount_myr'));

        $expectedClosing = BcmathHelper::subtract(
            BcmathHelper::add($openingFloat, $cashReceived),
            $cashPaidOut
        );

        $actualClosing = $this->sumDecimalColumn(
            $myrTillBalances->whereNotNull('closing_balance'),
            'closing_balance'
        );
        $hasClosingBalance = $myrTillBalances->whereNotNull('closing_balance')->isNotEmpty();

        if (! $hasClosingBalance) {
            // Session not yet closed, return expected closing to show pending variance
            return $expectedClosing;
        }

        return BcmathHelper::subtract($actualClosing, $expectedClosing);
    }

    /**
     * Generate formal reconciliation report with all details.
     *
     * @param  Carbon  $date  Reconciliation date
     * @param  int|null  $branchId  Optional branch filter
     * @param  int|null  $counterId  Optional specific counter
     * @return array Formal reconciliation report
     */
    public function generateReconciliationReport(Carbon $date, ?int $branchId = null, ?int $counterId = null): array
    {
        if ($counterId) {
            $report = $this->generateCounterReconciliation($counterId, $date);
            $report['report_type'] = 'counter';
        } else {
            $report = $this->generateDailyReconciliationSummary($date, $branchId);
            $report['report_type'] = 'daily';
        }

        // Add report metadata
        $report['report_metadata'] = [
            'generated_at' => now()->toIso8601String(),
            'report_date' => $date->toDateString(),
            'generated_by' => ActorContext::capture()->user?->username ?? 'System',
            'branch_filter' => $branchId,
            'counter_filter' => $counterId,
            'version' => '1.0',
        ];

        // Calculate variance status
        $report['variance_status'] = $this->determineVarianceStatus($report);

        return $report;
    }

    /**
     * Map preloaded till balances to the currency breakdown payload.
     *
     * @param  Collection<int, TillBalance>  $tillBalances
     * @return array<int, array<string, mixed>>
     */
    private function currencyBreakdownFromBalances(Collection $tillBalances): array
    {
        return $tillBalances->map(function ($balance) {
            return [
                'currency_code' => $balance->currency_code,
                'currency_name' => $balance->currency->name ?? $balance->currency_code,
                'opening_balance' => $balance->opening_balance,
                'closing_balance' => $balance->closing_balance,
                'variance' => $balance->variance,
            ];
        })->toArray();
    }

    /**
     * Sum a decimal column across an already-loaded collection using BCMath.
     *
     * Collection ->sum() adds in float space, which loses precision for
     * decimal(18,4) money columns; this keeps reconciliation totals exact.
     *
     * @param  iterable<object>  $items
     */
    private function sumDecimalColumn(iterable $items, string $column): string
    {
        $total = '0';

        foreach ($items as $item) {
            $total = BcmathHelper::add($total, (string) ($item->{$column} ?? '0'));
        }

        return $total;
    }

    /**
     * Determine variance status based on thresholds.
     *
     * @param  array  $report  Reconciliation report
     * @return array Variance status details
     */
    private function determineVarianceStatus(array $report): array
    {
        $variance = $report['variance'] ?? $report['totals']['variance'] ?? '0';
        $absVariance = BcmathHelper::abs((string) $variance);

        $status = 'ok';
        $severity = 'none';

        $redThreshold = $this->resolveVarianceThreshold('red', '500.00');
        $yellowThreshold = $this->resolveVarianceThreshold('yellow', '100.00');

        if (BcmathHelper::gt($absVariance, $redThreshold)) {
            $status = 'critical';
            $severity = 'red';
        } elseif (BcmathHelper::gt($absVariance, $yellowThreshold)) {
            $status = 'warning';
            $severity = 'yellow';
        } elseif (BcmathHelper::gt($absVariance, '0')) {
            $status = 'minor';
            $severity = 'orange';
        }

        return [
            'status' => $status,
            'severity' => $severity,
            'variance_amount' => $variance,
            'absolute_variance' => $absVariance,
            'threshold_red' => $redThreshold,
            'threshold_yellow' => $yellowThreshold,
        ];
    }

    /**
     * Check for Enhanced CDD transactions missing deferred accounting entries.
     * Returns transactions that are completed but have no journal entry linked.
     *
     * @param  Carbon  $date  Date to check
     * @param  int|null  $branchId  Optional branch filter
     * @return array Transactions missing accounting entries
     */
    public function checkMissingAccountingEntries(Carbon $date, ?int $branchId = null): array
    {
        $transactions = Transaction::with(['user', 'branch'])
            ->completed()
            ->whereBetween('approved_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->where('cdd_level', 'Enhanced')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->get();

        $missingEntries = $transactions->filter(function ($tx) {
            return $tx->journal_entry_id === null;
        });

        $hasIssues = $missingEntries->isNotEmpty();

        return [
            'date' => $date->toDateString(),
            'checked_at' => now()->toIso8601String(),
            'total_enhanced_ccd_transactions' => $transactions->count(),
            'missing_entries_count' => $missingEntries->count(),
            'has_issues' => $hasIssues,
            'transactions' => $missingEntries->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => $tx->type->value,
                    'currency_code' => $tx->currency_code,
                    'amount_myr' => $tx->amount_myr,
                    'quantity' => $tx->quantity,
                    'branch_id' => $tx->branch_id,
                    'branch_name' => $tx->branch?->name,
                    'user_id' => $tx->user_id,
                    'user_name' => $tx->user?->username,
                    'approved_at' => $tx->approved_at?->toIso8601String(),
                    'approved_by' => $tx->approved_by,
                    'journal_entry_id' => $tx->journal_entry_id,
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * Get count of transactions missing accounting entries for reporting.
     *
     * @param  Carbon  $date  Date to check
     * @param  int|null  $branchId  Optional branch filter
     * @return int Count of transactions missing entries
     */
    public function getMissingAccountingEntriesCount(Carbon $date, ?int $branchId = null): int
    {
        return Transaction::whereBetween('approved_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->completed()
            ->where('cdd_level', 'Enhanced')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->whereNull('journal_entry_id')
            ->count();
    }

    /**
     * Resolve a variance threshold as a numeric string for bcmath helpers.
     */
    private function resolveVarianceThreshold(string $key, string $fallback): string
    {
        $value = $this->thresholdService->get('variance', $key, $fallback);

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Configured variance {$key} threshold must be numeric.");
        }

        return (string) $value;
    }
}
