<?php

namespace App\Services\Accounting;

use App\Enums\SystemAlertLevel;
use App\Exceptions\Domain\AccountingPeriodException;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\CurrencyPosition;
use App\Models\RevaluationEntry;
use App\Services\AuditService;
use App\Services\System\MathService;
use App\Services\System\SystemAlertService;
use App\Services\ThresholdService;
use App\Services\Transaction\RateApiService;
use App\Support\ActorContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RevaluationService
{
    /**
     * Create a new RevaluationService instance.
     */
    public function __construct(
        protected MathService $mathService,
        protected RateApiService $rateApiService,
        protected AccountingService $accountingService,
        protected AuditService $auditService,
        protected SystemAlertService $alertService,
        protected ThresholdService $thresholdService,
    ) {}

    /**
     * Run revaluation for all currency positions in a branch.
     *
     * Calculates gain/loss for each currency position by comparing current
     * market rate with the current rate, then updates position records.
     *
     * @param  int  $postedBy  User ID performing the revaluation
     * @param  string|null  $branchId  Branch identifier (defaults to 'HQ')
     * @return array Array containing:
     *               - date: string Revaluation date (Y-m-d format)
     *               - branch_id: string Branch identifier
     *               - positions_revalued: int Number of positions processed
     *               - entries: array List of revaluation entry details
     */
    public function runRevaluation(int $postedBy, ?string $branchId = null): array
    {
        $branchId = $branchId ?? 'HQ';
        $revaluationDate = now()->toDateString();
        $results = [];

        // Pre-fetch all exchange rates in a single API call to avoid N+1 per position
        $this->rateApiService->fetchLatestRates();

        $positions = CurrencyPosition::where('branch_id', $branchId)
            ->where('quantity', '!=', '0')
            ->get();

        foreach ($positions as $position) {
            $result = $this->revaluePosition($position, $revaluationDate, $postedBy);
            if ($result) {
                $results[] = $result;
            }
        }

        // Log revaluation run event
        $this->auditService->logPositionEvent('position_revaluation_run', [
            'new' => [
                'date' => $revaluationDate,
                'branch_id' => $branchId,
                'positions_revalued' => count($results),
            ],
        ]);

        // Check for position limit breaches
        foreach ($results as $result) {
            $this->checkPositionLimitBreach($result, $branchId);
        }

        return [
            'date' => $revaluationDate,
            'branch_id' => $branchId,
            'positions_revalued' => count($results),
            'entries' => $results,
        ];
    }

    /**
     * Revalue a single currency position.
     *
     * Calculates gain/loss by comparing current market rate with last valuation rate,
     * creates a revaluation entry, and updates the position record.
     *
     * @param  CurrencyPosition  $position  The currency position to revalue
     * @param  string  $date  Revaluation date (Y-m-d format)
     * @param  int  $postedBy  User ID performing the revaluation
     * @return array|null Revaluation result array or null if no rate available
     */
    protected function revaluePosition(CurrencyPosition $position, string $date, int $postedBy): ?array
    {
        $newRate = $this->getCurrentRate($position->currency_code);
        if (! $newRate) {
            return null;
        }

        return DB::transaction(function () use ($position, $newRate, $date, $postedBy) {
            // Lock the position row and recompute under the lock so concurrent
            // revaluations of the same position cannot double-book entries at
            // the same rate (read-modify-write race on current_rate).
            $lockedPosition = CurrencyPosition::where('branch_id', $position->branch_id)
                ->where('currency_code', $position->currency_code)
                ->lockForUpdate()
                ->first();

            if (! $lockedPosition) {
                return null;
            }

            $oldRate = $lockedPosition->current_rate ?? $lockedPosition->average_cost;

            // Prevent double-counting: check if position was already revalued at this rate
            // (scale 8 — per-unit rates can differ only beyond 4 decimals).
            if ($lockedPosition->current_rate !== null && bccomp((string) $lockedPosition->current_rate, (string) $newRate, 8) === 0) {
                return null;
            }

            $gainLoss = $this->mathService->calculateRevaluationPnl(
                $lockedPosition->quantity,
                $oldRate,
                $newRate
            );

            // Unrealized P&L is the absolute mark-to-market value at the current rate:
            // quantity x (current_rate - average_cost). Recompute from the cost basis so
            // repeated revaluations never double-count, matching CurrencyPositionService.
            $unrealizedGainLoss = $this->mathService->calculateRevaluationPnl(
                $lockedPosition->quantity,
                $lockedPosition->average_cost,
                $newRate
            );

            // Create revaluation entry
            $entry = RevaluationEntry::create([
                'currency_code' => $lockedPosition->currency_code,
                'branch_id' => $lockedPosition->branch_id,
                'old_rate' => $oldRate,
                'new_rate' => $newRate,
                'position_amount' => $lockedPosition->quantity,
                'gain_loss_amount' => $gainLoss,
                'revaluation_date' => $date,
                'posted_by' => $postedBy,
            ]);

            // Update position — keep current_value consistent with the new
            // rate, as CurrencyPositionService::updatePosition maintains it.
            $lockedPosition->update([
                'current_rate' => $newRate,
                'current_value' => $this->mathService->round(
                    $this->mathService->multiply((string) $lockedPosition->quantity, (string) $newRate)
                ),
                'unrealized_gain_loss' => $unrealizedGainLoss,
                'last_revalued_at' => now(),
            ]);

            return [
                'entry_id' => $entry->id,
                'currency' => $lockedPosition->currency_code,
                'old_rate' => $oldRate,
                'new_rate' => $newRate,
                'gain_loss' => $gainLoss,
            ];
        });
    }

    /**
     * Get the current market rate for a currency.
     *
     * Retrieves the mid rate from the rate API service for revaluation purposes.
     *
     * @param  string  $currencyCode  The ISO currency code
     * @return numeric-string|null The mid rate as a string, or null if rate unavailable
     */
    protected function getCurrentRate(string $currencyCode): ?string
    {
        $rate = $this->rateApiService->getRateForCurrency($currencyCode);
        if (! $rate) {
            return null;
        }

        // Use mid rate for revaluation
        /** @var numeric-string|null $mid */
        $mid = isset($rate['mid']) ? (string) $rate['mid'] : null;

        return $mid;
    }

    /**
     * Generate a revaluation report for a specific date.
     *
     * Retrieves all revaluation entries for the given date and calculates
     * total gains, total losses, and net P&L.
     *
     * @param  string  $date  Date to generate report for (Y-m-d format)
     * @return array Array containing:
     *               - date: string Report date
     *               - entries: \Illuminate\Database\Eloquent\Collection Revaluation entries
     *               - total_gain: string Total gains (as string for precision)
     *               - total_loss: string Total losses (as string for precision)
     *               - net_pnl: string Net profit/loss (as string for precision)
     */
    public function getRevaluationReport(string $date): array
    {
        $entries = RevaluationEntry::where('revaluation_date', $date)
            ->with(['currency', 'postedBy'])
            ->get();

        $totalGain = '0';
        $totalLoss = '0';

        foreach ($entries as $entry) {
            $amount = $entry->gain_loss_amount;
            if ($this->mathService->compare($amount, '0') >= 0) {
                $totalGain = $this->mathService->add($totalGain, $amount);
            } else {
                $totalLoss = $this->mathService->add($totalLoss, $amount);
            }
        }

        return [
            'date' => $date,
            'entries' => $entries,
            'total_gain' => $totalGain,
            'total_loss' => $totalLoss,
            'net_pnl' => $this->mathService->add($totalGain, $totalLoss),
        ];
    }

    /**
     * Run revaluation with automatic journal entry creation.
     *
     * Performs revaluation for all positions and creates corresponding
     * journal entries for accounting purposes. Each currency is processed
     * in its own transaction to ensure data integrity.
     *
     * @param  string|null  $date  Revaluation date (defaults to current date)
     * @param  int|null  $postedBy  User ID performing the revaluation (defaults to authenticated user)
     * @return array Array containing:
     *               - date: string Revaluation date
     *               - positions_updated: int Number of positions processed
     *               - results: array List of revaluation results by currency
     *               - total_gain: string Total gains (as string for precision)
     *               - total_loss: string Total losses (as string for precision)
     *               - net_pnl: string Net profit/loss (as string for precision)
     *               - report_path: string|null Path to generated report (if any)
     *               - errors: array List of errors encountered during processing
     *
     * @throws \InvalidArgumentException If posting date falls outside an open period
     * @throws \RuntimeException If all revaluations fail
     */
    public function runRevaluationWithJournal(?string $date = null, ?int $postedBy = null): array
    {
        $date = $date ?? now()->toDateString();
        $postedBy = $postedBy ?? ActorContext::capture()->userId ?? config('cems.system_user_id', 1);

        $this->validatePeriodForDate($date);

        // Only positions with an open long balance need revaluing; pushing the
        // filter into the query avoids loading the full positions table.
        $positions = CurrencyPosition::where('quantity', '>', '0')->get();
        $results = [];
        $totalGain = '0';
        $totalLoss = '0';
        $errors = [];

        foreach ($positions as $position) {
            $attempt = $this->revaluePositionForJournal($position, $date, $postedBy);

            if ($attempt['error'] !== null) {
                $errors[] = $attempt['error'];

                continue;
            }
            if ($attempt['result'] === null) {
                continue;
            }

            if ($attempt['result']['is_gain']) {
                $totalGain = $this->mathService->add($totalGain, $attempt['result']['gain_loss']);
            } else {
                $totalLoss = $this->mathService->add($totalLoss, $attempt['result']['gain_loss']);
            }

            $results[] = $attempt['result'];
        }

        $this->assertNoRevaluationFailures($results, $errors);

        return [
            'date' => $date,
            'positions_updated' => count($results),
            'results' => $results,
            'total_gain' => $totalGain,
            'total_loss' => $totalLoss,
            'net_pnl' => $this->mathService->add($totalGain, $totalLoss),
            'report_path' => null,
            'has_failures' => false,
            'failed_currencies' => [],
        ];
    }

    /**
     * Throw a summary exception when any per-currency revaluation failed,
     * naming the successful and failed currencies.
     *
     * @param  array<int, array{currency_code: string}>  $results
     * @param  array<int, array{currency_code: string, error: string}>  $errors
     */
    protected function assertNoRevaluationFailures(array $results, array $errors): void
    {
        if (empty($errors)) {
            return;
        }

        $failedCodes = array_map(fn ($e) => $e['currency_code'], $errors);
        $successfulCodes = array_map(fn ($r) => $r['currency_code'], $results);

        $parts = [];
        if (! empty($successfulCodes)) {
            $parts[] = 'Successful currencies: '.implode(', ', $successfulCodes);
        }
        $parts[] = 'Failed currencies: '.implode(', ', $failedCodes);

        throw new AccountingPeriodException(implode("\n", $parts));
    }

    /**
     * Attempt the journal-backed revaluation of one position, converting a
     * thrown failure into an error descriptor so the caller can accumulate
     * and summarize failures after the loop.
     *
     * @return array{result: ?array{currency_code: string, gain_loss: string, is_gain: bool}, error: ?array{currency_code: string, error: string}}
     */
    protected function revaluePositionForJournal(CurrencyPosition $position, string $date, int $postedBy): array
    {
        if ($this->mathService->compare($position->quantity, '0') <= 0) {
            return ['result' => null, 'error' => null];
        }

        $newRate = $this->getCurrentRate($position->currency_code)
            ?? ($position->current_rate ?? $position->average_cost);

        if (! $newRate) {
            return ['result' => null, 'error' => null];
        }

        /** @var numeric-string $newRate */
        try {
            return [
                'result' => $this->revaluePositionUnderLock($position, $newRate, $date, $postedBy),
                'error' => null,
            ];
        } catch (\Exception $e) {
            $errorMessage = "Revaluation failed for {$position->currency_code}: {$e->getMessage()}";
            Log::error($errorMessage);

            return [
                'result' => null,
                'error' => [
                    'currency_code' => $position->currency_code,
                    'error' => $errorMessage,
                ],
            ];
        }
    }

    /**
     * Revalue a single position inside its own transaction. The row is
     * locked and every value recomputed under the lock so concurrent runs
     * cannot double-book the same revaluation.
     *
     * @param  numeric-string  $newRate
     * @return array{currency_code: string, gain_loss: string, is_gain: bool}|null
     */
    protected function revaluePositionUnderLock(CurrencyPosition $position, string $newRate, string $date, int $postedBy): ?array
    {
        return DB::transaction(function () use ($position, $newRate, $date, $postedBy) {
            $lockedPosition = CurrencyPosition::where('branch_id', $position->branch_id)
                ->where('currency_code', $position->currency_code)
                ->lockForUpdate()
                ->first();

            if (! $lockedPosition) {
                return null;
            }

            if ($this->alreadyRevaluedAt($lockedPosition, $newRate)) {
                return null;
            }

            $oldRate = $lockedPosition->current_rate ?? $lockedPosition->average_cost;
            $gainLoss = $this->mathService->calculateRevaluationPnl(
                $lockedPosition->quantity,
                $oldRate,
                $newRate
            );

            if ($this->mathService->compare($gainLoss, '0') === 0) {
                return null;
            }

            // Unrealized P&L is the absolute mark-to-market value at the new rate:
            // quantity x (new_rate - average_cost). Recomputed from the cost basis so
            // repeated revaluations never double-count, matching CurrencyPositionService.
            $unrealizedGainLoss = $this->mathService->calculateRevaluationPnl(
                $lockedPosition->quantity,
                $lockedPosition->average_cost,
                $newRate
            );

            $revaluationEntry = $this->recordEntry($lockedPosition, $oldRate, $newRate, $gainLoss, $date, $postedBy);

            $isGain = $this->mathService->compare($gainLoss, '0') > 0;
            $this->postRevaluationJournal($lockedPosition, $revaluationEntry, $newRate, $gainLoss, $isGain, $date, $postedBy);
            $this->markPositionRevalued($lockedPosition, $newRate, $unrealizedGainLoss);

            return [
                'currency_code' => $lockedPosition->currency_code,
                'gain_loss' => $gainLoss,
                'is_gain' => $isGain,
            ];
        });
    }

    /**
     * Dedup guard: another process already revalued this position at this
     * rate (scale 8 — per-unit rates can differ only beyond 4 decimals).
     *
     * @param  numeric-string  $newRate
     */
    protected function alreadyRevaluedAt(CurrencyPosition $position, string $newRate): bool
    {
        return $position->current_rate !== null
            && bccomp((string) $position->current_rate, (string) $newRate, 8) === 0;
    }

    protected function recordEntry(CurrencyPosition $position, string $oldRate, string $newRate, string $gainLoss, string $date, int $postedBy): RevaluationEntry
    {
        return RevaluationEntry::create([
            'currency_code' => $position->currency_code,
            'branch_id' => $position->branch_id,
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
            'position_amount' => $position->quantity,
            'gain_loss_amount' => $gainLoss,
            'revaluation_date' => $date,
            'posted_by' => $postedBy,
        ]);
    }

    protected function postRevaluationJournal(CurrencyPosition $position, RevaluationEntry $revaluationEntry, string $newRate, string $gainLoss, bool $isGain, string $date, int $postedBy): void
    {
        // Validate and get configured account codes
        $forexPositionAccount = $this->getValidatedAccountCode('accounting.forex_position_account');
        $gainAccount = $this->getValidatedAccountCode('accounting.revaluation_gain_account');
        $lossAccount = $this->getValidatedAccountCode('accounting.revaluation_loss_account');

        $lines = [
            [
                'account_code' => $forexPositionAccount,
                'debit' => $isGain ? $gainLoss : '0',
                'credit' => $isGain ? '0' : $this->mathService->multiply($gainLoss, '-1'),
                'description' => "Revaluation for {$position->currency_code} @ {$newRate}",
            ],
            [
                'account_code' => $isGain ? $gainAccount : $lossAccount,
                'debit' => $isGain ? '0' : $this->mathService->multiply($gainLoss, '-1'),
                'credit' => $isGain ? $gainLoss : '0',
                'description' => "Revaluation gain/loss for {$position->currency_code}",
            ],
        ];

        $this->accountingService->createJournalEntry(
            $lines,
            'Revaluation',
            $revaluationEntry->id,
            "Month-end revaluation: {$position->currency_code}",
            $date,
            $postedBy
        );
    }

    protected function markPositionRevalued(CurrencyPosition $position, string $newRate, string $unrealizedGainLoss): void
    {
        $position->update([
            'unrealized_gain_loss' => $unrealizedGainLoss,
            'current_rate' => $newRate,
            'current_value' => $this->mathService->round(
                $this->mathService->multiply((string) $position->quantity, (string) $newRate)
            ),
            'last_revalued_at' => now(),
        ]);
    }

    /**
     * Validate that the posting date falls within an open period.
     *
     * Checks that the given date falls within an existing accounting period
     * and that the period is currently open for posting.
     *
     * @param  string  $date  Date to validate (Y-m-d format)
     *
     * @throws \InvalidArgumentException If no period exists for the date
     * @throws \InvalidArgumentException If the period is closed
     */
    protected function validatePeriodForDate(string $date): void
    {
        // Find the accounting period for this entry date
        $period = AccountingPeriod::forDate($date)->first();

        // If no period exists for the date, throw exception
        if (! $period) {
            throw new AccountingPeriodException(
                "No accounting period found for date {$date}. Please create a period for this date or use a different date."
            );
        }

        // Validate that the period is open
        if (! $period->isOpen()) {
            throw new AccountingPeriodException(
                "Cannot post to closed period {$period->period_code}. Please use an open period or contact administrator."
            );
        }
    }

    /**
     * Schedule revaluation for month-end processing.
     *
     * Logs a notification that revaluation has been scheduled.
     * This method is typically called by scheduled tasks/cron jobs.
     */
    public function scheduleRevaluation(): void
    {
        Log::info('Revaluation scheduled for month-end');
    }

    /**
     * Get revaluation status for a specific month.
     *
     * Checks whether revaluation has been run for the given month
     * and provides summary information about the revaluation entries.
     *
     * @param  string  $month  Month to check (format: Y-m, e.g., "2024-01")
     * @return array Array containing:
     *               - month: string The queried month
     *               - has_run: bool Whether revaluation entries exist
     *               - entries_count: int Number of revaluation entries
     *               - currencies: array List of currency codes revalued
     */
    public function getRevaluationStatus(string $month): array
    {
        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();

        $entries = RevaluationEntry::whereBetween('revaluation_date', [$startDate, $endDate])
            ->get();

        return [
            'month' => $month,
            'has_run' => $entries->count() > 0,
            'entries_count' => $entries->count(),
            'currencies' => $entries->pluck('currency_code')->toArray(),
        ];
    }

    /**
     * Get validated account code from configuration.
     *
     * Retrieves account code from configuration and validates it exists
     * and is active in the chart of accounts when validation is enabled.
     *
     * @param  string  $configKey  Configuration key for the account code
     * @return string The validated account code
     *
     * @throws \InvalidArgumentException If account doesn't exist or is inactive (when validation enabled)
     */
    protected function getValidatedAccountCode(string $configKey): string
    {
        $code = Config::get($configKey);

        if ($code === null || $code === '') {
            throw new AccountingPeriodException("Account code '{$configKey}' is not configured. Set the corresponding ACCOUNT_* environment variable.");
        }

        if (Config::get('accounting.validate_accounts', true)) {
            $account = ChartOfAccount::where('account_code', $code)->first();

            if (! $account) {
                throw new AccountingPeriodException("Configured account '{$configKey}' with code '{$code}' does not exist in chart of accounts");
            }

            if (! $account->is_active) {
                throw new AccountingPeriodException("Configured account '{$configKey}' with code '{$code}' is not active");
            }
        }

        return $code;
    }

    /**
     * Check if a revaluation result breaches position limits.
     *
     * Logs a warning event and raises a SystemAlert (Warning when the breach
     * is within 10% of the limit, Critical beyond it).
     *
     * @param  array  $result  Revaluation result containing currency and gain/loss
     */
    protected function checkPositionLimitBreach(array $result, ?string $branchId = null): void
    {
        $currencyCode = $result['currency'] ?? null;
        $gainLossAmount = $result['gain_loss'] ?? '0';

        // Only alert if there's a gain (position increase)
        if ($this->mathService->compare($gainLossAmount, '0') <= 0) {
            return;
        }

        $limit = $this->thresholdService->getPositionLimit((string) $currencyCode);

        // Check if this currency has a configured limit
        if ($limit === null || $this->mathService->compare($gainLossAmount, $limit) <= 0) {
            return;
        }

        $positionLimit = $limit;
        $breachAmount = $this->mathService->subtract($gainLossAmount, $positionLimit);

        $this->auditService->logPositionEvent('position_limit_breach', [
            'new' => [
                'currency_code' => $currencyCode,
                'gain_loss' => $gainLossAmount,
                'limit' => $positionLimit,
                'breach_amount' => $breachAmount,
            ],
        ]);

        // Severity by breach magnitude: >10% over the limit is Critical.
        $overRatio = '0';
        if ($this->mathService->compare($positionLimit, '0') > 0) {
            $overRatio = $this->mathService->divide($breachAmount, $positionLimit);
        }
        $level = $this->mathService->compare($overRatio, '0.10') > 0 ? SystemAlertLevel::Critical : SystemAlertLevel::Warning;

        try {
            $this->alertService->send(
                "Position limit breached for {$currencyCode}"
                .($branchId !== null ? " at branch {$branchId}" : '')
                .": gain/loss {$gainLossAmount} exceeds limit {$positionLimit} (breach {$breachAmount})",
                $level->value,
                [
                    'source' => 'revaluation',
                    'metadata' => [
                        'branch_id' => $branchId,
                        'currency_code' => $currencyCode,
                        'gain_loss' => $gainLossAmount,
                        'limit' => $positionLimit,
                        'breach_amount' => $breachAmount,
                        'severity_ratio' => $overRatio,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to raise position-limit breach alert: '.$e->getMessage());
        }
    }
}
