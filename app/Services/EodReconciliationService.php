<?php

namespace App\Services;

use App\Enums\CounterSessionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterHandover;
use App\Models\CounterSession;
use App\Models\FlaggedTransaction;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Support\BcmathHelper;
use Carbon\Carbon;

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
            ->get();

        $counters = Counter::with('branch')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->active()
            ->get();

        $counterSummaries = [];
        $totalOpeningFloat = '0';
        $totalCashReceived = '0';
        $totalCashPaidOut = '0';
        $totalClosingExpected = '0';
        $totalClosingActual = '0';
        $totalVariance = '0';

        foreach ($counters as $counter) {
            $session = $sessions->where('counter_id', $counter->id)->first();

            if (! $session) {
                continue;
            }

            $summary = $this->generateCounterReconciliation($counter->id, $date);
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
            ->whereDate('created_at', $date->toDateString())
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->get();

        $largeTransactions = $transactions->filter(function ($tx) {
            return BcmathHelper::gte((string) $tx->amount_local, $this->thresholdService->getCtosThreshold());
        });

        $flaggedTransactions = FlaggedTransaction::with(['transaction', 'transaction.customer'])
            ->whereHas('transaction', function ($query) use ($date, $branchId) {
                $query->whereDate('created_at', $date->toDateString());
                if ($branchId) {
                    $query->where('branch_id', $branchId);
                }
            })
            ->where('status', '!=', 'Resolved')
            ->get();

        return [
            'date' => $date->toDateString(),
            'branch_id' => $branchId,
            'branch_name' => $branchId ? Branch::find($branchId)?->name : 'All Branches',
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
                'total_amount' => $largeTransactions->sum('amount_local'),
                'transactions' => $largeTransactions->take(50)->values(),
            ],
            'flagged_transactions' => [
                'count' => $flaggedTransactions->count(),
                'transactions' => $flaggedTransactions->take(50)->values(),
            ],
        ];
    }

    /**
     * Generate per-counter reconciliation details.
     *
     * @param  int  $counterId  Counter ID
     * @param  DateTime  $date  Reconciliation date
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

        // Get till balances for the day
        $tillBalances = TillBalance::where('till_id', (string) $counterId)
            ->where('date', $date->toDateString())
            ->get();

        $openingFloat = $tillBalances->sum('opening_balance');

        // Get transactions for this counter on this date
        $transactions = Transaction::with(['customer', 'user', 'flags'])
            ->where('till_id', (string) $counterId)
            ->whereDate('created_at', $date->toDateString())
            ->whereNotIn('status', [TransactionStatus::Cancelled->value, TransactionStatus::Failed->value])
            ->get();

        // Buy transactions = cash received (customer sells foreign currency, we buy)
        $buyTransactions = $transactions->filter(fn ($tx) => $tx->type === TransactionType::Buy);
        $totalCashReceived = $buyTransactions->sum('amount_local');

        // Sell transactions = cash paid out (customer buys foreign currency, we sell)
        $sellTransactions = $transactions->filter(fn ($tx) => $tx->type === TransactionType::Sell);
        $totalCashPaidOut = $sellTransactions->sum('amount_local');

        // Expected closing = opening + received - paid out
        $closingFloatExpected = BcmathHelper::subtract(
            BcmathHelper::add($openingFloat, $totalCashReceived),
            $totalCashPaidOut
        );

        // Actual closing from session close
        $closingFloatActual = $tillBalances->whereNotNull('closing_balance')->sum('closing_balance');
        $variance = $this->calculateVariance($counterId, $date);

        // Get handovers for the day
        $handovers = CounterHandover::with(['fromUser', 'toUser', 'supervisor'])
            ->whereHas('counterSession', function ($query) use ($counterId, $date) {
                $query->where('counter_id', $counterId)
                    ->where('session_date', $date->toDateString());
            })
            ->orderBy('handover_time', 'asc')
            ->get();

        // Currency breakdown
        $currencyBreakdown = $this->getCurrencyBreakdown($counterId, $date);

        // Large transactions (> RM 10k)
        $largeTransactions = $transactions->filter(function ($tx) {
            return BcmathHelper::gte((string) $tx->amount_local, $this->thresholdService->getCtosThreshold());
        });

        // Flagged transactions
        $flaggedTransactions = FlaggedTransaction::with(['transaction', 'transaction.customer'])
            ->whereHas('transaction', function ($query) use ($counterId, $date) {
                $query->where('till_id', (string) $counterId)
                    ->whereDate('created_at', $date->toDateString());
            })
            ->where('status', '!=', 'Resolved')
            ->get();

        return [
            'counter_id' => $counterId,
            'counter_code' => $counter->code,
            'counter_name' => $counter->name,
            'branch_name' => $counter->branch->name,
            'date' => $date->toDateString(),
            'has_session' => true,
            'session' => [
                'id' => $session->id,
                'status' => $session->status->value,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'closed_at' => $session->closed_at?->toIso8601String(),
                'opened_by' => $session->openedByUser ? [
                    'id' => $session->openedByUser->id,
                    'name' => $session->openedByUser->name,
                ] : null,
                'closed_by' => $session->closedByUser ? [
                    'id' => $session->closedByUser->id,
                    'name' => $session->closedByUser->name,
                ] : null,
                'current_user' => $session->user ? [
                    'id' => $session->user->id,
                    'name' => $session->user->name,
                ] : null,
            ],
            'opening_float' => $openingFloat,
            'total_cash_received' => $totalCashReceived,
            'total_cash_paid_out' => $totalCashPaidOut,
            'closing_float_expected' => $closingFloatExpected,
            'closing_float_actual' => $closingFloatActual ?: null,
            'variance' => $variance,
            'currency_breakdown' => $currencyBreakdown,
            'transactions' => [
                'total_count' => $transactions->count(),
                'buy_count' => $buyTransactions->count(),
                'sell_count' => $sellTransactions->count(),
                'buy_total' => $totalCashReceived,
                'sell_total' => $totalCashPaidOut,
            ],
            'large_transactions' => [
                'count' => $largeTransactions->count(),
                'total_amount' => $largeTransactions->sum('amount_local'),
                'transactions' => $largeTransactions->take(50)->values(),
            ],
            'flagged_transactions' => [
                'count' => $flaggedTransactions->count(),
                'transactions' => $flaggedTransactions->take(50)->values(),
            ],
            'handover_history' => $handovers->map(fn ($h) => [
                'id' => $h->id,
                'from_user' => $h->fromUser ? ['id' => $h->fromUser->id, 'name' => $h->fromUser->name] : null,
                'to_user' => $h->toUser ? ['id' => $h->toUser->id, 'name' => $h->toUser->name] : null,
                'supervisor' => $h->supervisor ? ['id' => $h->supervisor->id, 'name' => $h->supervisor->name] : null,
                'handover_time' => $h->handover_time?->toIso8601String(),
                'variance_myr' => $h->variance_myr,
                'physical_count_verified' => $h->physical_count_verified,
            ]),
        ];
    }

    /**
     * Calculate variance between expected and actual closing float.
     *
     * @param  int  $counterId  Counter ID
     * @param  DateTime  $date  Reconciliation date
     * @return string Variance amount (can be negative)
     */
    public function calculateVariance(int $counterId, Carbon $date): string
    {
        $tillBalances = TillBalance::where('till_id', (string) $counterId)
            ->where('date', $date->toDateString())
            ->get();

        $openingFloat = $tillBalances->sum('opening_balance');

        // Get transactions
        $transactions = Transaction::where('till_id', (string) $counterId)
            ->whereDate('created_at', $date->toDateString())
            ->whereNotIn('status', [TransactionStatus::Cancelled->value, TransactionStatus::Failed->value])
            ->get();

        $buyTotal = $transactions->filter(fn ($tx) => $tx->type === TransactionType::Buy)->sum('amount_local');
        $sellTotal = $transactions->filter(fn ($tx) => $tx->type === TransactionType::Sell)->sum('amount_local');

        $expectedClosing = BcmathHelper::subtract(
            BcmathHelper::add($openingFloat, $buyTotal),
            $sellTotal
        );

        $actualClosing = $tillBalances->whereNotNull('closing_balance')->sum('closing_balance');
        $hasClosingBalance = $tillBalances->whereNotNull('closing_balance')->isNotEmpty();

        if (! $hasClosingBalance) {
            // Session not yet closed, return expected closing to show pending variance
            return $expectedClosing;
        }

        return BcmathHelper::subtract($actualClosing, $expectedClosing);
    }

    /**
     * Generate formal reconciliation report with all details.
     *
     * @param  DateTime  $date  Reconciliation date
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
            'generated_by' => auth()->user()?->name ?? 'System',
            'branch_filter' => $branchId,
            'counter_filter' => $counterId,
            'version' => '1.0',
        ];

        // Calculate variance status
        $report['variance_status'] = $this->determineVarianceStatus($report);

        return $report;
    }

    /**
     * Get currency breakdown for a counter on a given date.
     *
     * @param  int  $counterId  Counter ID
     * @param  DateTime  $date  Reconciliation date
     * @return array Currency breakdown
     */
    private function getCurrencyBreakdown(int $counterId, Carbon $date): array
    {
        $tillBalances = TillBalance::with('currency')
            ->where('till_id', (string) $counterId)
            ->where('date', $date->toDateString())
            ->get();

        return $tillBalances->map(function ($balance) {
            return [
                'currency_code' => $balance->currency_code,
                'currency_name' => $balance->currency?->name ?? $balance->currency_code,
                'opening_balance' => $balance->opening_balance,
                'closing_balance' => $balance->closing_balance,
                'variance' => $balance->variance,
            ];
        })->toArray();
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
        $absVariance = BcmathHelper::abs($variance);

        $status = 'ok';
        $severity = 'none';

        $redThreshold = $this->thresholdService->get('variance', 'red', '500.00');
        $yellowThreshold = $this->thresholdService->get('variance', 'yellow', '100.00');

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
            ->whereDate('approved_at', $date->toDateString())
            ->where('status', TransactionStatus::Completed->value)
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
                    'amount_local' => $tx->amount_local,
                    'amount_foreign' => $tx->amount_foreign,
                    'branch_id' => $tx->branch_id,
                    'branch_name' => $tx->branch?->name,
                    'user_id' => $tx->user_id,
                    'user_name' => $tx->user?->name,
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
        return Transaction::whereDate('approved_at', $date->toDateString())
            ->where('status', TransactionStatus::Completed->value)
            ->where('cdd_level', 'Enhanced')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->whereNull('journal_entry_id')
            ->count();
    }
}
