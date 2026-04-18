<?php

namespace App\Services;

use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\TillAlreadyOpenException;
use App\Exceptions\Domain\UserAlreadyAtCounterException;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\User;
use App\Support\BcmathHelper;
use Exception;
use Illuminate\Support\Facades\DB;

class CounterService
{
    public function __construct(
        protected TellerAllocationService $tellerAllocationService,
    ) {}

    private const VARIANCE_THRESHOLD_YELLOW = 100.00;

    private const VARIANCE_THRESHOLD_RED = 500.00;

    /**
     * Open a counter session
     *
     * Wrapped in a transaction with locking to prevent race conditions
     * where two users could open the same counter simultaneously.
     */
    public function openSession(Counter $counter, User $user, array $openingFloats): CounterSession
    {
        $now = now();
        $today = $now->toDateString();

        return DB::transaction(function () use ($counter, $user, $openingFloats, $now, $today) {
            // Lock and check if counter is already open (prevents race condition)
            $existingSession = CounterSession::where('counter_id', $counter->id)
                ->where('status', CounterSessionStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if ($existingSession) {
                throw new TillAlreadyOpenException($counter->code ?? (string) $counter->id);
            }

            // Lock and check if user is already at another counter
            $userSession = CounterSession::where('user_id', $user->id)
                ->where('status', CounterSessionStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if ($userSession) {
                throw new UserAlreadyAtCounterException($user->id);
            }

            // Pre-fetch all currencies to avoid N+1 queries
            $currencies = $this->resolveCurrencies($openingFloats);

            // Create session
            $session = CounterSession::create([
                'counter_id' => $counter->id,
                'user_id' => $user->id,
                'session_date' => $today,
                'opened_at' => $now,
                'opened_by' => $user->id,
                'status' => CounterSessionStatus::Open,
            ]);

            foreach ($openingFloats as $float) {
                $currencyCode = $currencies[$float['currency_id']] ?? null;

                if ($currencyCode) {
                    TillBalance::create([
                        'till_id' => (string) $counter->id,
                        'currency_code' => $currencyCode,
                        'opening_balance' => $float['amount'],
                        'date' => $today,
                        'opened_by' => $user->id,
                    ]);
                }
            }

            return $session;
        });
    }

    /**
     * Close a counter session
     *
     * Validates variance thresholds, updates till balances, and closes the session
     * atomically within a single database transaction.
     */
    public function closeSession(CounterSession $session, User $user, array $closingFloats, ?string $notes = null, ?User $supervisor = null): CounterSession
    {
        if (! $session->isOpen()) {
            throw new Exception('Session is not open');
        }

        $now = now();

        return DB::transaction(function () use ($session, $user, $closingFloats, $notes, $supervisor, $now) {
            // Pre-fetch all currencies and till balances to avoid N+1
            $currencyIds = collect($closingFloats)->pluck('currency_id')->unique()->toArray();

            // Separate numeric IDs from string codes
            $numericIds = array_filter($currencyIds, 'is_numeric');
            $stringCodes = array_diff($currencyIds, $numericIds);

            // Build proper query to avoid OR condition returning all records
            $currencies = Currency::where(function ($query) use ($stringCodes, $numericIds) {
                if (! empty($stringCodes)) {
                    $query->whereIn('code', $stringCodes);
                }
                if (! empty($numericIds)) {
                    $query->orWhereIn(Currency::getModel()->getKeyName(), $numericIds);
                }
            })->get()->keyBy('code');

            $tillBalances = TillBalance::where('till_id', (string) $session->counter_id)
                ->where('date', $session->session_date)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->get()
                ->keyBy('currency_code');

            // Single pass: validate variance AND collect update data
            $updates = [];
            foreach ($closingFloats as $float) {
                $currency = $currencies->get($float['currency_id'])
                    ?? $currencies->first(fn ($c) => $c->getKey() == $float['currency_id']);

                if (! $currency) {
                    continue;
                }

                $tillBalance = $tillBalances->get($currency->code);
                $openingBalance = $tillBalance ? $tillBalance->opening_balance : '0';
                $foreignTotal = $tillBalance && $tillBalance->foreign_total !== null
                    ? $tillBalance->foreign_total
                    : '0';
                $expectedBalance = BcmathHelper::add($openingBalance, $foreignTotal);

                $closingBalance = $float['amount'];
                $variance = BcmathHelper::subtract($closingBalance, $expectedBalance);

                // Validate variance thresholds
                if (BcmathHelper::gt(BcmathHelper::abs($variance), (string) self::VARIANCE_THRESHOLD_RED)) {
                    if (! $supervisor || ! $supervisor->isManager()) {
                        throw new Exception('Variance exceeds red threshold, requires supervisor approval');
                    }
                } elseif (BcmathHelper::gt(BcmathHelper::abs($variance), (string) self::VARIANCE_THRESHOLD_YELLOW)) {
                    if (empty($notes)) {
                        throw new Exception('Variance exceeds yellow threshold, requires explanation notes');
                    }
                }

                // Collect update data for the second phase
                if ($tillBalance) {
                    $updates[] = [
                        'tillBalance' => $tillBalance,
                        'closingBalance' => $closingBalance,
                        'variance' => $variance,
                    ];
                }
            }

            // Phase 2: Apply all updates atomically (session + till balances together)
            $session->update([
                'closed_at' => $now,
                'closed_by' => $user->id,
                'status' => CounterSessionStatus::Closed,
                'notes' => $notes,
            ]);

            foreach ($updates as $update) {
                $update['tillBalance']->update([
                    'closing_balance' => $update['closingBalance'],
                    'variance' => $update['variance'],
                    'closed_at' => $now,
                    'closed_by' => $user->id,
                    'notes' => $notes,
                ]);
            }

            return $session;
        });
    }

    /**
     * Calculate variance between expected and actual.
     * Uses BCMath for precision, returns float for backward compatibility.
     */
    public function calculateVariance(string $expected, string $actual): string
    {
        return BcmathHelper::subtract($actual, $expected);
    }

    /**
     * Close a counter session and return teller allocation to branch pool.
     *
     * This is the EOD workflow that:
     * 1. Gets the teller allocation linked to this session
     * 2. Calls closeSession() to perform variance calculation
     * 3. Returns the allocation to the branch pool
     * 4. Returns the closed session
     */
    public function closeSessionAndReturnToPool(
        CounterSession $session,
        User $user,
        array $closingFloats,
        ?string $notes = null,
        ?User $supervisor = null
    ): CounterSession {
        $allocation = $session->tellerAllocation;

        $closedSession = $this->closeSession($session, $user, $closingFloats, $notes, $supervisor);

        if ($allocation) {
            $allocation->returnToPool();
        }

        return $closedSession;
    }

    /**
     * Get counter status
     */
    public function getCounterStatus(Counter $counter): array
    {
        $session = CounterSession::where('counter_id', $counter->id)
            ->where('status', CounterSessionStatus::Open->value)
            ->first();

        return [
            'counter' => $counter,
            'status' => $session ? CounterSessionStatus::Open->value : CounterSessionStatus::Closed->value,
            'current_user' => $session ? $session->user : null,
            'session' => $session,
        ];
    }

    /**
     * Get available counters
     */
    public function getAvailableCounters(): array
    {
        $allCounters = Counter::active()->get();
        $openCounterIds = CounterSession::where('status', CounterSessionStatus::Open->value)
            ->pluck('counter_id')
            ->toArray();

        return $allCounters->filter(function ($counter) use ($openCounterIds) {
            return ! in_array($counter->id, $openCounterIds);
        })->values()->all();
    }

    /**
     * Initiate handover between users
     *
     * Closes the current session, creates a handover record, and opens a new
     * session for the receiving user — all atomically within a transaction.
     */
    public function initiateHandover(
        CounterSession $session,
        User $fromUser,
        User $toUser,
        User $supervisor,
        array $physicalCounts
    ): array {
        // Validate supervisor role
        if (! $supervisor->isManager()) {
            throw new Exception('Supervisor must be a manager or admin');
        }

        // Validate session is open
        if (! $session->isOpen()) {
            throw new Exception('Session is not open');
        }

        // Validate fromUser is the session user
        if ($session->user_id !== $fromUser->id) {
            throw new Exception('Session does not belong to the specified user');
        }

        // Validate toUser is not already at another counter (with lock to prevent race condition)
        $existingSession = CounterSession::where('user_id', $toUser->id)
            ->where('status', CounterSessionStatus::Open->value)
            ->lockForUpdate()
            ->first();

        if ($existingSession && $existingSession->id !== $session->id) {
            throw new Exception('User is already at another counter');
        }

        $now = now();
        $today = $now->toDateString();

        return DB::transaction(function () use ($session, $fromUser, $toUser, $supervisor, $physicalCounts, $now, $today) {
            // Pre-fetch all currencies to avoid N+1
            $currencies = $this->resolveCurrenciesForCounts($physicalCounts);

            // Pre-fetch relevant till balances
            $currencyCodes = array_values(array_filter($currencies));
            $tillBalances = TillBalance::where('till_id', (string) $session->counter_id)
                ->where('date', $session->session_date)
                ->whereNull('closed_at')
                ->whereIn('currency_code', $currencyCodes)
                ->get()
                ->keyBy('currency_code');

            // ============================================================
            // HANDOVER TILL BALANCES - Revised for correctness
            // ============================================================
            // Lock all relevant till balances upfront to prevent race conditions
            $allBalances = TillBalance::where('till_id', (string) $session->counter_id)
                ->where('date', $today)
                ->whereIn('currency_code', $currencyCodes)
                ->lockForUpdate()
                ->get()
                ->keyBy('currency_code');

            $openBalances = $allBalances->filter(fn ($b) => is_null($b->closed_at));
            $closedBalances = $allBalances->filter(fn ($b) => ! is_null($b->closed_at));

            $totalVarianceMyr = '0';
            $perCurrencyVariances = [];

            // Pre-fetch latest exchange rates for foreign currencies (use rate_sell for conversion)
            $exchangeRates = [];
            $nonMyrCodes = array_filter($currencyCodes, fn ($c) => $c !== 'MYR');
            if (! empty($nonMyrCodes)) {
                $rates = ExchangeRate::whereIn('currency_code', $nonMyrCodes)
                    ->orderBy('fetched_at', 'desc')
                    ->get()
                    ->unique('currency_code'); // keep latest per currency due to orderBy desc
                foreach ($rates as $rate) {
                    $exchangeRates[$rate->currency_code] = $rate->rate_sell;
                }
            }

            // Phase A: Compute variances for each currency
            foreach ($physicalCounts as $count) {
                $currencyCode = $currencies[$count['currency_id']] ?? null;
                if (! $currencyCode) {
                    continue;
                }

                $closingBalance = $count['amount'];
                $balanceRow = $openBalances->get($currencyCode) ?? $closedBalances->get($currencyCode);

                if ($balanceRow) {
                    $opening = $balanceRow->opening_balance;
                    $foreign = $balanceRow->foreign_total ?? '0';
                    $expected = BcmathHelper::add($opening, $foreign);
                    $variance = BcmathHelper::subtract($closingBalance, $expected);
                } else {
                    // No prior balance; variance is zero (new currency added)
                    $variance = '0.0000';
                }

                $perCurrencyVariances[$currencyCode] = $variance;

                // Convert to MYR for total (use last_rate if available, else 1)
                if ($currencyCode === 'MYR') {
                    $totalVarianceMyr = BcmathHelper::add($totalVarianceMyr, $variance);
                } else {
                    // Use latest exchange rate (rate_sell) to convert foreign variance to MYR
                    $rate = $exchangeRates[$currencyCode] ?? '1';
                    $varianceMyr = BcmathHelper::multiply($variance, $rate);
                    $totalVarianceMyr = BcmathHelper::add($totalVarianceMyr, $varianceMyr);
                }
            }

            // Validate variance thresholds (only red threshold requires supervisor)
            foreach ($perCurrencyVariances as $code => $variance) {
                $absVar = BcmathHelper::abs($variance);
                if (BcmathHelper::gt($absVar, (string) self::VARIANCE_THRESHOLD_RED)) {
                    if (! $supervisor || ! $supervisor->isManager()) {
                        throw new Exception('Variance exceeds red threshold, requires supervisor approval');
                    }
                }
                // Yellow threshold does not block handover; it's for information
            }

            // Build variance notes
            $varianceNotes = 'Variance during handover: ';
            foreach ($perCurrencyVariances as $code => $v) {
                $varianceNotes .= "{$code}: {$v}; ";
            }

            // Phase B: Apply updates using the locked rows
            foreach ($physicalCounts as $count) {
                $currencyCode = $currencies[$count['currency_id']] ?? null;
                if (! $currencyCode) {
                    continue;
                }

                $closingBalance = $count['amount'];
                $variance = $perCurrencyVariances[$currencyCode];
                $open = $openBalances->get($currencyCode);
                $closed = $closedBalances->get($currencyCode);

                if ($open) {
                    // Close the old open balance
                    $open->update([
                        'closing_balance' => $closingBalance,
                        'variance' => $variance,
                        'closed_at' => $now,
                        'closed_by' => $fromUser->id,
                        'notes' => 'Handover',
                    ]);

                    // Create new open balance for new session
                    TillBalance::create([
                        'till_id' => (string) $session->counter_id,
                        'currency_code' => $currencyCode,
                        'opening_balance' => $closingBalance,
                        'date' => $today,
                        'opened_by' => $toUser->id,
                    ]);
                } elseif ($closed) {
                    // Reopen the closed balance for new session
                    $closed->update([
                        'opening_balance' => $closingBalance,
                        'closing_balance' => null,
                        'variance' => '0.0000',
                        'closed_at' => null,
                        'closed_by' => null,
                        'notes' => null,
                        'opened_by' => $toUser->id,
                    ]);
                } else {
                    // No existing balance at all - create new
                    TillBalance::create([
                        'till_id' => (string) $session->counter_id,
                        'currency_code' => $currencyCode,
                        'opening_balance' => $closingBalance,
                        'date' => $today,
                        'opened_by' => $toUser->id,
                    ]);
                }
            }

            // Mark old session as handed over
            $session->update([
                'status' => CounterSessionStatus::HandedOver,
                'closed_at' => $now,
                'closed_by' => $fromUser->id,
            ]);

            // Create handover record
            $handover = $session->handovers()->create([
                'from_user_id' => $fromUser->id,
                'to_user_id' => $toUser->id,
                'supervisor_id' => $supervisor->id,
                'handover_time' => $now,
                'physical_count_verified' => true,
                'variance_myr' => $totalVarianceMyr,
                'variance_notes' => BcmathHelper::compare($totalVarianceMyr, '0') !== 0 ? $varianceNotes : null,
            ]);

            // Create new session for incoming user (must be after old session closed)
            $newSession = CounterSession::create([
                'counter_id' => $session->counter_id,
                'user_id' => $toUser->id,
                'session_date' => $today,
                'opened_at' => $now,
                'opened_by' => $supervisor->id,
                'status' => CounterSessionStatus::Open,
            ]);

            // Transfer teller allocations
            $activeAllocations = TellerAllocation::where('user_id', $fromUser->id)
                ->where('status', TellerAllocationStatus::ACTIVE->value)
                ->whereDate('session_date', $today)
                ->get();

            foreach ($activeAllocations as $allocation) {
                $this->tellerAllocationService->transferToTeller($allocation, $toUser);
            }

            return ['handover' => $handover, 'new_session' => $newSession];
        });
    }

    /**
     * Resolve currency codes from opening floats, handling both numeric IDs and string codes.
     * Returns a map of [input_id => currency_code].
     */
    private function resolveCurrencies(array $floats): array
    {
        $ids = collect($floats)->pluck('currency_id')->unique()->toArray();

        $numericIds = array_filter($ids, 'is_numeric');
        $stringCodes = array_filter($ids, fn ($id) => ! is_numeric($id));

        $resolved = [];

        // Map string codes directly
        foreach ($stringCodes as $code) {
            $resolved[$code] = $code;
        }

        // Look up numeric IDs
        if (! empty($numericIds)) {
            $currencies = Currency::whereIn('id', $numericIds)->pluck('code', 'id');
            foreach ($currencies as $id => $code) {
                $resolved[$id] = $code;
            }
        }

        return $resolved;
    }

    /**
     * Resolve currency codes from physical counts array.
     * Returns a map of [input_id => currency_code].
     * Handles both numeric IDs and string codes for consistency.
     */
    private function resolveCurrenciesForCounts(array $counts): array
    {
        $ids = collect($counts)->pluck('currency_id')->unique()->toArray();

        $numericIds = array_filter($ids, 'is_numeric');
        $stringCodes = array_filter($ids, fn ($id) => ! is_numeric($id));

        $resolved = [];

        // Map string codes directly
        foreach ($stringCodes as $code) {
            $resolved[$code] = $code;
        }

        // Look up numeric IDs
        if (! empty($numericIds)) {
            $currencies = Currency::whereIn('id', $numericIds)->pluck('code', 'id');
            foreach ($currencies as $id => $code) {
                $resolved[$id] = $code;
            }
        }

        return $resolved;
    }
}
