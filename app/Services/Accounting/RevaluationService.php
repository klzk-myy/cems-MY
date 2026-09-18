<?php

namespace App\Services\Accounting;

use App\Enums\AccountMappingKey;
use App\Enums\SystemAlertLevel;
use App\Exceptions\Domain\AccountingPeriodException;
use App\Models\AccountingPeriod;
use App\Models\CurrencyPosition;
use App\Models\RevaluationEntry;
use App\Services\AuditService;
use App\Services\System\MathService;
use App\Services\System\SystemAlertService;
use App\Services\ThresholdService;
use App\Services\Transaction\RateApiService;
use App\Support\ActorContext;
use Carbon\Carbon;
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
        protected AccountMappingService $accountMappingService,
    ) {}

    /**
     * Get the current market rate for a currency.
     *
     * The live provider mid is preferred (mark-to-market). When the provider is
     * unavailable — EXCHANGE_RATE_API_KEY unset, a provider outage, or a
     * currency the provider does not quote — the branch's published card is
     * used instead, so one provider problem cannot fail every position in a
     * revaluation run (previously the provider was the sole source).
     *
     * @param  string  $currencyCode  The ISO currency code
     * @param  int|null  $branchId  Branch whose card should price the position
     * @return numeric-string|null The mid rate as a string, or null if rate unavailable
     */
    protected function getCurrentRate(string $currencyCode, ?int $branchId = null): ?string
    {
        try {
            $rate = $this->rateApiService->getRateForCurrency($currencyCode);

            if ($rate && isset($rate['mid'])) {
                /** @var numeric-string $mid */
                $mid = (string) $rate['mid'];

                return $mid;
            }
        } catch (\Exception $e) {
            Log::warning('Live exchange rate unavailable for revaluation; falling back to the stored rate card', [
                'currency_code' => $currencyCode,
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
            ]);
        }

        // Provider-less fallback: the branch card (company card when the branch
        // publishes none), already normalized to a per-unit mid at scale 8.
        return $this->rateApiService->getCurrentRate($currencyCode, 'mid', $branchId);
    }

    /**
     * Branch rate card that prices a position.
     *
     * currency_positions.branch_id is a nullable branch id — a value maps to
     * that branch's card while null resolves to the company-wide card.
     */
    protected function rateBranchId(CurrencyPosition $position): ?int
    {
        return $position->branch_id;
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
        $postedBy = $postedBy ?? ActorContext::capture()->userIdOrSystem();

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

        // Position-limit breach alerts (previously only reachable via the
        // removed non-journal runRevaluation path). Limits are denominated in
        // foreign-currency units, so the position quantity is what is compared.
        foreach ($results as $result) {
            $this->checkPositionLimitBreach([
                'currency' => $result['currency_code'],
                'quantity' => $result['quantity'],
            ], $result['branch_id'] ?? null);
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
     * @return array{result: ?array{currency_code: string, branch_id: int|null, quantity: string, gain_loss: string, is_gain: bool}, error: ?array{currency_code: string, error: string}}
     */
    protected function revaluePositionForJournal(CurrencyPosition $position, string $date, int $postedBy): array
    {
        $newRate = $this->getCurrentRate($position->currency_code, $this->rateBranchId($position))
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
     * @return array{currency_code: string, branch_id: int|null, quantity: string, gain_loss: string, is_gain: bool}|null
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
                'branch_id' => $lockedPosition->branch_id,
                'quantity' => $lockedPosition->quantity,
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
        // Resolve account codes from the account_mappings table (editable
        // under Accounting → Account Mappings; defaults match the chart).
        $forexPositionAccount = $this->accountMappingService->code(AccountMappingKey::RevaluationPosition);
        $gainAccount = $this->accountMappingService->code(AccountMappingKey::RevaluationGain);
        $lossAccount = $this->accountMappingService->code(AccountMappingKey::RevaluationLoss);

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

        // Positions carry the branch id directly; a null (company-wide)
        // position books its revaluation P&L on the company-wide chain.
        $branchId = $position->branch_id;

        $this->accountingService->createJournalEntry(
            $lines,
            'Revaluation',
            $revaluationEntry->id,
            "Month-end revaluation: {$position->currency_code}",
            $date,
            $postedBy,
            $branchId
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
     * Check if a revaluation result breaches position limits.
     *
     * Logs a warning event and raises a SystemAlert (Warning when the breach
     * is within 10% of the limit, Critical beyond it). Position limits are
     * denominated in foreign-currency units, matching the buy-side check in
     * TransactionCreationService.
     *
     * @param  array  $result  Revaluation result containing currency and position quantity
     */
    protected function checkPositionLimitBreach(array $result, int|string|null $branchId = null): void
    {
        $currencyCode = $result['currency'] ?? null;
        $positionAmount = $result['quantity'] ?? '0';

        if ($this->mathService->compare($positionAmount, '0') <= 0) {
            return;
        }

        $limit = $this->thresholdService->getPositionLimit((string) $currencyCode);

        // Check if this currency has a configured limit
        if ($limit === null || $this->mathService->compare($positionAmount, $limit) <= 0) {
            return;
        }

        $positionLimit = $limit;
        $breachAmount = $this->mathService->subtract($positionAmount, $positionLimit);

        $this->auditService->logPositionEvent('position_limit_breach', [
            'new' => [
                'currency_code' => $currencyCode,
                'quantity' => $positionAmount,
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
                .": position {$positionAmount} exceeds limit {$positionLimit} (breach {$breachAmount})",
                $level->value,
                [
                    'source' => 'revaluation',
                    'metadata' => [
                        'branch_id' => $branchId,
                        'currency_code' => $currencyCode,
                        'quantity' => $positionAmount,
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
