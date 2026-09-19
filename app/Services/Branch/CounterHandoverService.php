<?php

namespace App\Services\Branch;

use App\Enums\CounterSessionStatus;
use App\Enums\Permission;
use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\BusinessDateFrozenException;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\SessionClosedException;
use App\Exceptions\Domain\SessionOwnershipException;
use App\Exceptions\Domain\SupervisorRequiredException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Exceptions\Domain\UserAlreadyAtCounterException;
use App\Exceptions\Domain\VarianceThresholdException;
use App\Models\BranchClosureWorkflow;
use App\Models\CounterHandover;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\Branch\DTOs\HandoverVarianceResult;
use App\Services\ThresholdService;
use App\Support\BcmathHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CounterHandoverService
{
    public function __construct(
        protected TellerAllocationService $tellerAllocationService,
        protected ThresholdService $thresholdService,
        protected HandoverVarianceCalculator $varianceCalculator,
    ) {}

    /**
     * Hand a counter session over to another teller.
     *
     * Closes the current session, rotates the till balances, records the
     * handover, and opens a new session for the receiving user — all
     * atomically within a transaction.
     *
     * @param  array<int, array{currency_id: mixed, quantity: string}>  $physicalCounts
     * @return array{handover: CounterHandover, new_session: CounterSession}
     */
    public function initiateHandover(
        CounterSession $session,
        User $fromUser,
        User $toUser,
        User $supervisor,
        array $physicalCounts
    ): array {
        $this->assertCanHandover($session, $fromUser, $supervisor);

        $now = now();
        $today = $now->toDateString();
        $sessionBranchId = $session->counter?->branch_id;

        return DB::transaction(function () use ($session, $fromUser, $toUser, $supervisor, $physicalCounts, $now, $today, $sessionBranchId) {
            // A finalized day close freezes the branch's books — a
            // successor session cannot be minted on a frozen business
            // date. Checked under the workflow-row lock so a racing
            // finalize serializes against the handover.
            if ($sessionBranchId !== null && BranchClosureWorkflow::freezesDateForUpdate($sessionBranchId, $today)) {
                throw new BusinessDateFrozenException(
                    $session->counter->branch->code ?? (string) $sessionBranchId,
                    $today
                );
            }

            $this->assertRecipientIsFree($session, $toUser);

            $currencies = $this->resolveCurrenciesForCounts($physicalCounts);
            $currencyCodes = array_values(array_filter($currencies));
            // Sort upfront so the lock order below is stable across
            // concurrent handovers with different currency sets.
            sort($currencyCodes);

            $tillCode = $session->tillCode();
            $balances = $this->lockTillBalances($tillCode, $session->session_date, $currencyCodes);
            $openBalances = $balances->filter(fn ($b) => is_null($b->closed_at));
            $closedBalances = $balances->filter(fn ($b) => ! is_null($b->closed_at));

            $variances = $this->varianceCalculator->compute(
                $physicalCounts,
                $currencies,
                $openBalances,
                $closedBalances,
                $this->latestSellRates($currencyCodes, $this->handoverBranchId($openBalances, $session))
            );
            $hasYellowVariance = $this->assertVarianceThresholds($variances->perCurrency, $supervisor);

            $this->rotateTillBalances(
                $physicalCounts,
                $currencies,
                $variances,
                $openBalances,
                $closedBalances,
                $tillCode,
                $session,
                $fromUser,
                $toUser,
                $now,
                $today
            );

            $session->update([
                'status' => CounterSessionStatus::PendingHandover,
                'closed_at' => $now,
                'closed_by' => $fromUser->id,
            ]);

            $handover = $this->recordHandover(
                $session, $fromUser, $toUser, $supervisor, $now, $variances, $hasYellowVariance
            );
            $newSession = $this->openSuccessorSession($session, $toUser, $supervisor, $now, $today);
            $this->transferAllocations($fromUser, $toUser, $today);

            return ['handover' => $handover, 'new_session' => $newSession];
        });
    }

    /**
     * Preconditions evaluated before the transaction opens: supervisor
     * capability, open session, and session ownership.
     */
    private function assertCanHandover(CounterSession $session, User $fromUser, User $supervisor): void
    {
        if (! $supervisor->role->canPerform(Permission::ManageCounters)) {
            throw new SupervisorRequiredException;
        }

        if (! $session->isOpen()) {
            throw new SessionClosedException;
        }

        if ($session->user_id !== $fromUser->id) {
            throw new SessionOwnershipException;
        }
    }

    /**
     * The recipient must not hold an open session at another counter. The
     * row is locked inside the transaction so concurrent handovers cannot
     * assign the same teller twice.
     */
    private function assertRecipientIsFree(CounterSession $session, User $toUser): void
    {
        $existingSession = CounterSession::where('user_id', $toUser->id)
            ->where('status', CounterSessionStatus::Open->value)
            ->lockForUpdate()
            ->first();

        if ($existingSession && $existingSession->id !== $session->id) {
            throw new UserAlreadyAtCounterException($toUser->id);
        }
    }

    /**
     * Lock all relevant till balances for the session's till/date in
     * deterministic (alphabetical) order to prevent deadlocks.
     *
     * @param  array<int, string>  $currencyCodes
     * @return Collection<string, TillBalance> keyed by currency_code
     */
    private function lockTillBalances(string $tillCode, string $sessionDate, array $currencyCodes): Collection
    {
        return TillBalance::where('till_id', $tillCode)
            // $sessionDate arrives as 'Y-m-d' or a datetime string (Carbon
            // coerced through the string hint) — normalize to a date so the
            // whereDate comparison matches both storage formats.
            ->whereDate('date', Carbon::parse($sessionDate)->toDateString())
            ->whereIn('currency_code', $currencyCodes)
            ->orderBy('currency_code')
            ->lockForUpdate()
            ->get()
            ->keyBy('currency_code');
    }

    /**
     * Latest per-unit sell rate for each non-MYR currency code, resolved
     * against the branch's own card (falling back to the company-wide card).
     * An unscoped lookup valued a handover on whichever branch's rate happened
     * to be newest.
     *
     * @param  array<int, string>  $currencyCodes
     * @return array<string, string> currency_code => per-unit rate
     */
    private function latestSellRates(array $currencyCodes, ?int $branchId = null): array
    {
        $nonMyrCodes = array_filter($currencyCodes, fn ($c) => $c !== Currency::baseCurrency());

        if (empty($nonMyrCodes)) {
            return [];
        }

        $query = ExchangeRate::whereIn('currency_code', $nonMyrCodes)->active();

        if ($branchId !== null) {
            $query->forBranchOrCompany($branchId);
        }

        return $query
            ->orderByRaw('branch_id IS NULL')
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('currency_code')
            ->map(fn ($group) => $group->first())
            // exchange_rates rows are unit-quoted (per rate_unit foreign
            // units, or foreign per rate_unit MYR when inverse); normalize
            // to per-unit for conversion.
            ->map(fn ($rate) => $rate->perUnitRate((string) $rate->rate_sell))
            ->all();
    }

    /**
     * The branch whose card prices this handover: the till's branch, falling
     * back to the counter's branch.
     *
     * @param  Collection<string, TillBalance>  $openBalances
     */
    private function handoverBranchId(Collection $openBalances, CounterSession $session): ?int
    {
        $firstOpen = $openBalances->first();
        $branchId = ($firstOpen ? $firstOpen->branch_id : null) ?? $session->counter?->branch_id;

        return $branchId === null ? null : (int) $branchId;
    }

    /**
     * Red-threshold variance aborts unless the supervisor can manage
     * counters (they already can — assertCanHandover ran first); yellow
     * does not block but is flagged for acknowledgment.
     *
     * @param  array<string, string>  $perCurrencyVariances
     */
    private function assertVarianceThresholds(array $perCurrencyVariances, User $supervisor): bool
    {
        $hasYellowVariance = false;

        foreach ($perCurrencyVariances as $variance) {
            $absVar = BcmathHelper::abs($variance);
            if (BcmathHelper::gt($absVar, $this->thresholdService->getVarianceRedThreshold())) {
                if (! $supervisor->role->canPerform(Permission::ManageCounters)) {
                    throw new VarianceThresholdException('red', true);
                }
            } elseif (BcmathHelper::gt($absVar, $this->thresholdService->getVarianceYellowThreshold())) {
                $hasYellowVariance = true;
            }
        }

        return $hasYellowVariance;
    }

    /**
     * Apply the physical counts: close open balances and open successor
     * balances for the incoming teller, or reopen previously closed ones.
     *
     * @param  array<int, array{currency_id: mixed, quantity: string}>  $physicalCounts
     * @param  array<mixed, string>  $currencies  input currency_id => currency_code
     * @param  Collection<string, TillBalance>  $openBalances
     * @param  Collection<string, TillBalance>  $closedBalances
     */
    private function rotateTillBalances(
        array $physicalCounts,
        array $currencies,
        HandoverVarianceResult $variances,
        Collection $openBalances,
        Collection $closedBalances,
        string $tillCode,
        CounterSession $session,
        User $fromUser,
        User $toUser,
        Carbon $now,
        string $today
    ): void {
        foreach ($physicalCounts as $count) {
            $currencyCode = $currencies[$count['currency_id']] ?? null;
            if (! $currencyCode) {
                continue;
            }

            $closingBalance = $count['quantity'];
            $variance = $variances->perCurrency[$currencyCode];
            $open = $openBalances->get($currencyCode);
            $closed = $closedBalances->get($currencyCode);

            if ($open) {
                // Carry variance details on the closed row only for the
                // currency that actually varied.
                $open->update([
                    'closing_balance' => $closingBalance,
                    'variance' => $variance,
                    'closed_at' => $now,
                    'closed_by' => $fromUser->id,
                    'notes' => BcmathHelper::isNotZero($variance) ? $variances->notes : 'Handover',
                ]);

                TillBalance::openFor(
                    $tillCode,
                    $currencyCode,
                    $session->counter?->branch_id,
                    $closingBalance,
                    $today,
                    $toUser->id
                );
            } elseif ($closed) {
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
                TillBalance::openFor(
                    $tillCode,
                    $currencyCode,
                    $session->counter?->branch_id,
                    $closingBalance,
                    $today,
                    $toUser->id
                );
            }
        }
    }

    private function recordHandover(
        CounterSession $session,
        User $fromUser,
        User $toUser,
        User $supervisor,
        Carbon $now,
        HandoverVarianceResult $variances,
        bool $hasYellowVariance
    ): CounterHandover {
        return $session->handovers()->create([
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'supervisor_id' => $supervisor->id,
            'handover_time' => $now,
            'physical_count_verified' => true,
            'variance_myr' => $variances->totalMyr,
            'variance_notes' => $variances->hasVariance() ? $variances->notes : null,
            'yellow_variance' => $hasYellowVariance,
        ]);
    }

    private function openSuccessorSession(
        CounterSession $session,
        User $toUser,
        User $supervisor,
        Carbon $now,
        string $today
    ): CounterSession {
        return CounterSession::create([
            'counter_id' => $session->counter_id,
            'user_id' => $toUser->id,
            'session_date' => $today,
            'opened_at' => $now,
            'opened_by' => $supervisor->id,
            'status' => CounterSessionStatus::Open,
        ]);
    }

    /**
     * Move the outgoing teller's active allocations for today to the
     * incoming teller.
     */
    private function transferAllocations(User $fromUser, User $toUser, string $today): void
    {
        TellerAllocation::query()
            ->with(['counter', 'user', 'branch'])
            ->where('user_id', $fromUser->id)
            ->where('status', TellerAllocationStatus::Active->value)
            ->whereDate('session_date', $today)
            ->get()
            ->each(fn (TellerAllocation $allocation) => $this->tellerAllocationService->transferToTeller($allocation, $toUser));
    }

    /**
     * Resolve currency codes from physical counts array.
     * Returns a map of [input_id => currency_code].
     * Handles both numeric IDs and string codes for consistency.
     *
     * @param  array<int, array{currency_id: mixed, quantity: string}>  $counts
     * @return array<mixed, string>
     */
    private function resolveCurrenciesForCounts(array $counts): array
    {
        $ids = collect($counts)->pluck('currency_id')->unique()->toArray();

        $numericIds = array_filter($ids, 'is_numeric');
        $stringCodes = array_filter($ids, fn ($id) => ! is_numeric($id));

        $resolved = [];

        foreach ($stringCodes as $code) {
            $resolved[$code] = $code;
        }

        if (! empty($numericIds)) {
            $currencies = Currency::whereIn('id', $numericIds)->pluck('code', 'id');
            foreach ($currencies as $id => $code) {
                $resolved[$id] = $code;
                $resolved[(string) $id] = $code;
                $resolved[$code] = $code;
            }
        }

        return $resolved;
    }

    public function findPendingHandover(int $userId, int $counterId, string $date): ?CounterHandover
    {
        return CounterHandover::with(['counterSession', 'fromUser', 'supervisor'])
            ->whereHas('counterSession', function ($query) use ($counterId, $date) {
                $query->where('counter_id', $counterId)
                    ->whereDate('session_date', $date);
            })
            ->where(function ($query) use ($userId) {
                $query->where('to_user_id', $userId)
                    ->orWhere('supervisor_id', $userId);
            })
            ->whereNull('acknowledged_at')
            ->first();
    }

    public function acknowledgeHandover(
        CounterHandover $handover,
        User $user,
        bool $verified,
        ?string $notes
    ): void {
        // The designated recipient confirms receipt of custody, or the
        // recorded supervisor may confirm on their behalf. The previous
        // manager-only rule deadlocked: findPendingHandover scopes to
        // to_user_id, so a teller recipient could never acknowledge.
        if ($user->id !== $handover->to_user_id && $user->id !== $handover->supervisor_id) {
            throw new UnauthorizedException('Only the designated recipient or the supervisor can acknowledge this handover');
        }

        DB::transaction(function () use ($handover, $verified, $notes) {
            // Lock order is handover row -> session row; every concurrent
            // acknowledgment serializes on the handover lock first.
            $locked = CounterHandover::query()
                ->whereKey($handover->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new InvalidStateException('Handover not found');
            }

            $session = CounterSession::query()
                ->whereKey($locked->counter_session_id)
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw new InvalidStateException('Handover counter session not found');
            }

            // Re-validate pending state AFTER acquiring locks so a racing
            // acknowledgment that already committed makes this one fail.
            if ($locked->acknowledged_at !== null || $session->status !== CounterSessionStatus::PendingHandover) {
                throw new InvalidStateException('Handover is not pending acknowledgment');
            }

            // Yellow variance requires explicit acknowledgment (S7)
            if ($locked->yellow_variance && ! $verified) {
                throw new InvalidStateException('Yellow variance requires acknowledgment');
            }

            // Return previous teller's allocation to branch pool
            $fromAllocation = TellerAllocation::where('user_id', $locked->from_user_id)
                ->where('status', TellerAllocationStatus::Active)
                ->whereDate('session_date', now()->toDateString())
                ->first();

            if ($fromAllocation) {
                $this->tellerAllocationService->returnToPool($fromAllocation);
            }

            // Activate new teller's allocation
            $toAllocation = TellerAllocation::where('user_id', $locked->to_user_id)
                ->where('status', TellerAllocationStatus::Approved)
                ->whereDate('session_date', now()->toDateString())
                ->first();

            if ($toAllocation) {
                $this->tellerAllocationService->activateAllocation($toAllocation);
            }

            $session->update([
                'status' => CounterSessionStatus::HandedOver,
                'physical_count_verified' => $verified,
                'handover_notes' => $notes,
            ]);

            $locked->update(['acknowledged_at' => now()]);
        });
    }
}
