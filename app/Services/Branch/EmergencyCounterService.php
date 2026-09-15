<?php

namespace App\Services\Branch;

use App\Enums\CounterSessionStatus;
use App\Enums\Permission;
use App\Exceptions\Domain\EmergencyCloseCooldownException;
use App\Exceptions\Domain\EmergencyCloseSessionTooNewException;
use App\Exceptions\Domain\NoActiveCounterSessionException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\EmergencyClosure;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\User;
use App\Notifications\EmergencyCounterClosureNotification;
use App\Services\AuditService;
use App\Services\System\MathService;
use Illuminate\Support\Facades\DB;

class EmergencyCounterService
{
    public function __construct(
        protected TellerAllocationService $allocationService,
        protected AuditService $auditService,
        protected MathService $mathService,
    ) {}

    public function initiateEmergencyClose(Counter $counter, User $teller, string $reason): EmergencyClosure
    {
        $this->validateConstraints($counter, $teller);

        $closure = DB::transaction(function () use ($counter, $teller, $reason) {
            // Re-fetch the session under a row lock inside the transaction:
            // two concurrent closes must not both observe Open and double-
            // insert an EmergencyClosure for the same session.
            $session = CounterSession::where('counter_id', $counter->id)
                ->whereDate('session_date', now()->toDateString())
                ->where('status', CounterSessionStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw new NoActiveCounterSessionException($counter->id, now()->toDateString());
            }

            $closure = EmergencyClosure::create([
                'counter_id' => $counter->id,
                'session_id' => $session->id,
                'teller_id' => $teller->id,
                'reason' => $reason,
                'closed_at' => now(),
            ]);

            $session->update([
                'status' => CounterSessionStatus::EmergencyClosed,
            ]);

            // Only roll an ACTIVE allocation back into the pool - returning a
            // returned/rejected/closed allocation would clobber its status.
            $tellerAllocation = $session->tellerAllocation;

            if ($tellerAllocation instanceof TellerAllocation && $tellerAllocation->status->isActive()) {
                $this->allocationService->returnToPool($tellerAllocation);
            }

            return $closure;
        });

        $this->notifyManager($closure);

        $this->auditService->logEmergencyClosureEvent(
            'emergency_counter_close',
            $closure->id,
            [
                'user_id' => $teller->id,
                'new_values' => [
                    'counter_code' => $counter->code,
                    'teller_id' => $teller->id,
                    'reason' => $reason,
                    'session_id' => $closure->session_id,
                ],
            ],
            'WARNING'
        );

        return $closure;
    }

    private function validateConstraints(Counter $counter, User $teller): void
    {
        $recent = EmergencyClosure::where('counter_id', $counter->id)
            ->where('created_at', '>=', now()->subHours(4))
            ->exists();
        if ($recent) {
            throw new EmergencyCloseCooldownException;
        }

        $openSessions = CounterSession::where('counter_id', $counter->id)
            ->whereDate('session_date', now()->toDateString())
            ->where('status', CounterSessionStatus::Open->value)
            ->first();
        if (! $openSessions) {
            throw new NoActiveCounterSessionException($counter->id);
        }
        if ($openSessions->opened_at && $openSessions->opened_at->diffInMinutes(now()) < 30) {
            throw new EmergencyCloseSessionTooNewException;
        }
    }

    private function notifyManager(EmergencyClosure $closure): void
    {
        $managers = User::whereIn('role', ['manager', 'admin'])
            ->where('branch_id', $closure->counter->branch_id)
            ->where('is_active', true)
            ->get();

        foreach ($managers as $manager) {
            $manager->notify(new EmergencyCounterClosureNotification($closure));
        }
    }

    public function acknowledge(EmergencyClosure $closure, User $manager): EmergencyClosure
    {
        if (! $manager->role->canPerform(Permission::ManageCounters)) {
            throw new UnauthorizedException('Only users permitted to manage counters can acknowledge emergency closures');
        }

        $closure->update([
            'acknowledged_by' => $manager->id,
            'acknowledged_at' => now(),
        ]);

        $this->auditService->logEmergencyClosureEvent(
            'emergency_counter_close_acknowledged',
            $closure->id,
            [
                'user_id' => $manager->id,
                'new_values' => ['acknowledged_by' => $manager->id],
            ],
            'INFO'
        );

        return $closure;
    }

    public function getVariance(EmergencyClosure $closure): array
    {
        $session = $closure->session;
        $counter = $closure->counter;

        $tillBalances = TillBalance::where('till_id', (string) $counter->code)
            ->where('date', $session->session_date)
            ->get();

        $variance = [];
        foreach ($tillBalances as $balance) {
            $expected = $balance->getExpectedBalance();

            $actual = $balance->closing_balance ?? $balance->opening_balance;
            $diff = $this->mathService->subtract($actual, $expected);

            $variance[$balance->currency_code] = [
                'expected' => $expected,
                'actual' => $actual,
                'variance' => $diff,
            ];
        }

        return $variance;
    }
}
