<?php

namespace App\Services\Branch;

use App\Enums\CounterSessionStatus;
use App\Enums\CounterStatus;
use App\Enums\Permission;
use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\SessionClosedException;
use App\Exceptions\Domain\TillAlreadyOpenException;
use App\Exceptions\Domain\UserAlreadyAtCounterException;
use App\Exceptions\Domain\VarianceThresholdException;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ThresholdService;
use App\Support\BcmathHelper;
use Illuminate\Support\Facades\DB;

class CounterService
{
    public function __construct(
        protected TellerAllocationService $tellerAllocationService,
        protected ThresholdService $thresholdService,
        protected AuditService $auditService,
    ) {}

    /**
     * Register a counter at a trading branch.
     *
     * Shared by the web and API V1 controllers so both surfaces produce
     * identical outcomes and audit trails.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCounter(array $data, User $actor): Counter
    {
        $branch = Branch::findOrFail((int) $data['branch_id']);

        if (! $branch->canTrade()) {
            throw new InvalidStateException('Head office branches cannot have trading counters.');
        }

        $counter = Counter::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'status' => $data['status'] ?? CounterStatus::Active->value,
            'branch_id' => $branch->id,
        ]);

        $this->auditService->log(
            'counter_created',
            $actor->id,
            'Counter',
            $counter->id,
            [],
            [
                'code' => $counter->code,
                'name' => $counter->name,
                'branch_id' => $branch->id,
                'branch_code' => $branch->code,
            ]
        );

        return $counter;
    }

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
                    // Guard against duplicates: an open till row for
                    // (counter, currency, today) may already exist if it was
                    // opened through TillBalanceManager::openTill first.
                    $existing = TillBalance::where('till_id', (string) $counter->code)
                        ->where('currency_code', $currencyCode)
                        ->whereDate('date', $today)
                        ->whereNull('closed_at')
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        throw new TillAlreadyOpenException((string) $counter->code);
                    }

                    TillBalance::openFor(
                        (string) $counter->code,
                        $currencyCode,
                        $counter->branch_id,
                        $float['amount'],
                        $today,
                        $user->id
                    );
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
            throw new SessionClosedException;
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

            $tillBalances = TillBalance::where('till_id', $session->tillCode())
                ->whereDate('date', $session->session_date)
                ->whereNull('closed_at')
                ->orderBy('currency_code')
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
                // For foreign currency: expected = opening + buy_total_foreign - sell_total_foreign
                // This correctly handles position: buys increase stock, sells decrease stock
                $buyTotal = $tillBalance && $tillBalance->buy_total_foreign !== null
                    ? $tillBalance->buy_total_foreign : '0';
                $sellTotal = $tillBalance && $tillBalance->sell_total_foreign !== null
                    ? $tillBalance->sell_total_foreign : '0';
                $netForeign = BcmathHelper::subtract($buyTotal, $sellTotal);
                $expectedBalance = BcmathHelper::add($openingBalance, $netForeign);

                $closingBalance = $float['amount'];
                $variance = BcmathHelper::subtract($closingBalance, $expectedBalance);

                // Validate variance thresholds
                if (BcmathHelper::gt(BcmathHelper::abs($variance), $this->thresholdService->getVarianceRedThreshold())) {
                    if (! $supervisor || ! $supervisor->role->canPerform(Permission::ManageCounters)) {
                        throw new VarianceThresholdException('red', true);
                    }
                } elseif (BcmathHelper::gt(BcmathHelper::abs($variance), $this->thresholdService->getVarianceYellowThreshold())) {
                    if (empty($notes)) {
                        throw new VarianceThresholdException('yellow', false);
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

            // Closing counts the drawer back into branch holdings, so the
            // session teller's remaining allocation custody — including any
            // stock still loaded into this till — returns to the pool here.
            // Without this the loaded portion would stay earmarked to a
            // teller who no longer holds it.
            TellerAllocation::where('user_id', $session->user_id)
                ->where('status', TellerAllocationStatus::ACTIVE->value)
                ->get()
                ->each(fn (TellerAllocation $allocation) => $this->tellerAllocationService->returnToPool($allocation));

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
     * Kept for the explicit EOD workflow name: closeSession() already
     * returns every active allocation of the session teller to the pool
     * (including stock loaded into the till), so this simply delegates.
     */
    public function closeSessionAndReturnToPool(
        CounterSession $session,
        User $user,
        array $closingFloats,
        ?string $notes = null,
        ?User $supervisor = null
    ): CounterSession {
        return $this->closeSession($session, $user, $closingFloats, $notes, $supervisor);
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
     * Resolve currency codes from opening floats, handling both numeric IDs and string codes.
     * Returns a map of [input_id => currency_code].
     */
    private function resolveCurrencies(array $floats): array
    {
        $ids = collect($floats)->pluck('currency_id')->unique()->toArray();

        $numericIds = array_filter($ids, 'is_numeric');
        $stringCodes = array_filter($ids, fn ($id) => ! is_numeric($id));

        $resolved = [];

        foreach ($stringCodes as $code) {
            $resolved[$code] = $code;
        }

        if (! empty($numericIds)) {
            // Cast to ints for query
            $numericIds = array_map('intval', $numericIds);
            $currencies = Currency::whereIn('id', $numericIds)->pluck('code', 'id');
            foreach ($currencies as $id => $code) {
                $resolved[$id] = $code;         // int key
                $resolved[(string) $id] = $code; // string numeric key
                $resolved[$code] = $code;
            }
        }

        return $resolved;
    }
}
