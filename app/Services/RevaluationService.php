<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\CurrencyPosition;
use App\Models\RevaluationEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
    ) {}

    /**
     * Run revaluation for all currency positions in a till.
     *
     * Calculates gain/loss for each currency position by comparing current
     * market rate with the last valuation rate, then updates position records.
     *
     * @param  int  $postedBy  User ID performing the revaluation
     * @param  string|null  $tillId  Till identifier (defaults to 'MAIN')
     * @return array Array containing:
     *               - date: string Revaluation date (Y-m-d format)
     *               - till_id: string Till identifier
     *               - positions_revalued: int Number of positions processed
     *               - entries: array List of revaluation entry details
     */
    public function runRevaluation(int $postedBy, ?string $tillId = null): array
    {
        $tillId = $tillId ?? 'MAIN';
        $revaluationDate = now()->toDateString();
        $results = [];

        $positions = CurrencyPosition::where('till_id', $tillId)
            ->where('balance', '!=', 0)
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
                'till_id' => $tillId,
                'positions_revalued' => count($results),
            ],
        ]);

        // Check for position limit breaches
        foreach ($results as $result) {
            $this->checkPositionLimitBreach($result);
        }

        return [
            'date' => $revaluationDate,
            'till_id' => $tillId,
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

        $oldRate = $position->last_valuation_rate ?? $position->avg_cost_rate;
        $gainLoss = $this->mathService->calculateRevaluationPnl(
            $position->balance,
            $oldRate,
            $newRate
        );

        return DB::transaction(function () use ($position, $oldRate, $newRate, $gainLoss, $date, $postedBy) {
            // Prevent double-counting: check if position was already revalued at this rate
            if ($position->last_valuation_rate !== null && bccomp($position->last_valuation_rate, $newRate, 6) === 0) {
                return null;
            }

            // Create revaluation entry
            $entry = RevaluationEntry::create([
                'currency_code' => $position->currency_code,
                'till_id' => $position->till_id,
                'old_rate' => $oldRate,
                'new_rate' => $newRate,
                'position_amount' => $position->balance,
                'gain_loss_amount' => $gainLoss,
                'revaluation_date' => $date,
                'posted_by' => $postedBy,
            ]);

            // Update position
            $cumulativePnl = $this->mathService->add(
                $position->unrealized_pnl ?? '0',
                $gainLoss
            );
            $position->update([
                'last_valuation_rate' => $newRate,
                'unrealized_pnl' => $cumulativePnl,
                'last_valuation_at' => now(),
            ]);

            return [
                'entry_id' => $entry->id,
                'currency' => $position->currency_code,
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
     * @return string|null The mid rate as a string, or null if rate unavailable
     */
    protected function getCurrentRate(string $currencyCode): ?string
    {
        $rate = $this->rateApiService->getRateForCurrency($currencyCode);
        if (! $rate) {
            return null;
        }

        // Use mid rate for revaluation
        return (string) $rate['mid'];
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
     *
     * @throws \InvalidArgumentException If posting date falls outside an open period
     * @throws \RuntimeException If any revaluation fails (errors are not silently swallowed)
     */
    public function runRevaluationWithJournal(?string $date = null, ?int $postedBy = null): array
    {
        $date = $date ?? now()->toDateString();
        $postedBy = $postedBy ?? auth()->id() ?? 1;

        // FAULT #5 FIX: Validate period before processing
        $this->validatePeriodForDate($date);

        $positions = CurrencyPosition::all();
        $results = [];
        $totalGain = '0';
        $totalLoss = '0';
        $errors = [];

        // FAULT #6 FIX: Process each currency independently in its own transaction
        foreach ($positions as $position) {
            if ($this->mathService->compare($position->balance, '0') <= 0) {
                continue;
            }

            $oldRate = $position->last_valuation_rate ?? $position->avg_cost_rate;
            $newRate = $this->getCurrentRate($position->currency_code) ?? $oldRate;

            if (! $newRate) {
                continue;
            }

            $gainLoss = $this->mathService->calculateRevaluationPnl(
                $position->balance,
                $oldRate,
                $newRate
            );

            if ($this->mathService->compare($gainLoss, '0') === 0) {
                continue;
            }

            // Process each currency in its own transaction
            try {
                DB::transaction(function () use ($position, $oldRate, $newRate, $gainLoss, $date, $postedBy) {
                    $revaluationEntry = RevaluationEntry::create([
                        'currency_code' => $position->currency_code,
                        'till_id' => $position->till_id,
                        'old_rate' => $oldRate,
                        'new_rate' => $newRate,
                        'position_amount' => $position->balance,
                        'gain_loss_amount' => $gainLoss,
                        'revaluation_date' => $date,
                        'posted_by' => $postedBy,
                    ]);

                    // Validate and get configured account codes
                    $forexPositionAccount = $this->getValidatedAccountCode('accounting.forex_position_account', '2000');
                    $gainAccount = $this->getValidatedAccountCode('accounting.revaluation_gain_account', '5100');
                    $lossAccount = $this->getValidatedAccountCode('accounting.revaluation_loss_account', '6100');

                    $isGain = $this->mathService->compare($gainLoss, '0') > 0;
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

                    // FAULT #5 FIX: AccountingService will assign period_id to journal entry
                    $this->accountingService->createJournalEntry(
                        $lines,
                        'Revaluation',
                        $revaluationEntry->id,
                        "Month-end revaluation: {$position->currency_code}",
                        $date,
                        $postedBy
                    );

                    $position->update([
                        'unrealized_pnl' => $this->mathService->add($position->unrealized_pnl ?? '0', $gainLoss),
                        'last_valuation_rate' => $newRate,
                        'last_valuation_at' => now(),
                    ]);

                    return [
                        'currency_code' => $position->currency_code,
                        'gain_loss' => $gainLoss,
                        'is_gain' => $isGain,
                    ];
                });

                $isGain = $this->mathService->compare($gainLoss, '0') > 0;

                if ($isGain) {
                    $totalGain = $this->mathService->add($totalGain, $gainLoss);
                } else {
                    $totalLoss = $this->mathService->add($totalLoss, $gainLoss);
                }

                $results[] = [
                    'currency_code' => $position->currency_code,
                    'gain_loss' => $gainLoss,
                    'is_gain' => $isGain,
                ];
            } catch (\Exception $e) {
                // FAULT #6 FIX: Log error and continue processing other currencies
                $errorMessage = "Revaluation failed for {$position->currency_code}: {$e->getMessage()}";
                Log::error($errorMessage);
                $errors[] = [
                    'currency_code' => $position->currency_code,
                    'error' => $errorMessage,
                ];
            }
        }

        // If there are any errors, throw exception so caller MUST handle them
        // Include both successful and failed currencies in the error for traceability
        if (! empty($errors)) {
            $successfulCurrencies = array_column($results, 'currency_code');
            $failedCurrencies = array_column($errors, 'currency_code');
            $errorMsg = 'Revaluation errors occurred: '.implode('; ', array_column($errors, 'error'));
            $errorMsg .= '. Successful currencies: '.(empty($successfulCurrencies) ? 'none' : implode(', ', $successfulCurrencies));
            $errorMsg .= '. Failed currencies: '.implode(', ', $failedCurrencies);
            throw new \RuntimeException($errorMsg);
        }

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
     * FAULT #5 FIX: Validate that the posting date falls within an open period.
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
            throw new \InvalidArgumentException(
                "No accounting period found for date {$date}. Please create a period for this date or use a different date."
            );
        }

        // Validate that the period is open
        if (! $period->isOpen()) {
            throw new \InvalidArgumentException(
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
     * Send revaluation completion notification to recipients.
     *
     * Sends email notifications to all active users with revaluation
     * summary information. Attachments are included if a report path
     * is provided in the results.
     *
     * @param  array  $results  Revaluation results array containing:
     *                          - date: string Revaluation date
     *                          - positions_updated: int Number of positions updated
     *                          - net_pnl: string Net profit/loss
     *                          - report_path: string|null Path to report file
     */
    public function sendRevaluationNotification(array $results): void
    {
        $recipients = $this->getNotificationRecipients();

        foreach ($recipients as $recipient) {
            try {
                Mail::raw("Revaluation Complete\n\nDate: {$results['date']}\nPositions Updated: {$results['positions_updated']}\nNet P&L: {$results['net_pnl']}", function ($message) use ($recipient, $results) {
                    $message->to($recipient['email'])
                        ->subject('Monthly Revaluation Complete - '.now()->format('F Y'));

                    if (! empty($results['report_path'])) {
                        $message->attach($results['report_path']);
                    }
                });
            } catch (\Exception $e) {
                Log::error('Failed to send revaluation notification', [
                    'recipient' => $recipient['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get list of users to notify about revaluation completion.
     *
     * Retrieves only authorized users (managers, accountants, compliance officers)
     * who should receive sensitive financial P&L data. Regular tellers are excluded.
     *
     * @return array Array of user data arrays with email addresses
     */
    protected function getNotificationRecipients(): array
    {
        // Only managers, accountants, and compliance officers should receive P&L data
        return User::where('is_active', true)
            ->whereIn('role', [
                UserRole::Manager->value,
                UserRole::ComplianceOfficer->value,
                UserRole::Admin->value,
            ])
            ->get()
            ->toArray();
    }

    /**
     * Get validated account code from configuration.
     *
     * Retrieves account code from configuration and validates it exists
     * and is active in the chart of accounts when validation is enabled.
     *
     * @param  string  $configKey  Configuration key for the account code
     * @param  string  $defaultCode  Default account code to use if config not set
     * @return string The validated account code
     *
     * @throws \InvalidArgumentException If account doesn't exist or is inactive (when validation enabled)
     */
    protected function getValidatedAccountCode(string $configKey, string $defaultCode): string
    {
        $code = Config::get($configKey, $defaultCode);

        if (Config::get('accounting.validate_accounts', true)) {
            $account = ChartOfAccount::where('account_code', $code)->first();

            if (! $account) {
                throw new \InvalidArgumentException("Configured account '{$configKey}' with code '{$code}' does not exist in chart of accounts");
            }

            if (! $account->is_active) {
                throw new \InvalidArgumentException("Configured account '{$configKey}' with code '{$code}' is not active");
            }
        }

        return $code;
    }

    /**
     * Check if a revaluation result breaches position limits.
     *
     * Logs a warning event if the position balance exceeds configured limits.
     *
     * @param  array  $result  Revaluation result containing currency and gain/loss
     */
    protected function checkPositionLimitBreach(array $result): void
    {
        $currencyCode = $result['currency'] ?? null;
        $gainLossAmount = $result['gain_loss'] ?? '0';

        // Only log if there's a gain (position increase)
        if ($this->mathService->compare($gainLossAmount, '0') <= 0) {
            return;
        }

        $limits = config('cems.position_limits', []);

        // Check if this currency has a configured limit
        if (isset($limits[$currencyCode]) && bccomp($gainLossAmount, (string) $limits[$currencyCode], 2) > 0) {
            $positionLimit = $limits[$currencyCode];
            $this->auditService->logPositionEvent('position_limit_breach', [
                'new' => [
                    'currency_code' => $currencyCode,
                    'gain_loss' => $gainLossAmount,
                    'limit' => $positionLimit,
                    'breach_amount' => $this->mathService->subtract($gainLossAmount, (string) $positionLimit),
                ],
            ]);
        }
    }
}
