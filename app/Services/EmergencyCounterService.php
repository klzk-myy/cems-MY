<?php

namespace App\Services;

use App\Enums\CounterSessionStatus;
use App\Exceptions\Domain\EmergencyCloseCooldownException;
use App\Exceptions\Domain\EmergencyCloseSessionTooNewException;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\EmergencyClosure;
use App\Models\TillBalance;
use App\Models\User;
use App\Notifications\EmergencyCounterClosureNotification;

class EmergencyCounterService
{
    public function __construct(
        protected TellerAllocationService $allocationService,
        protected AuditService $auditService,
    ) {}

    public function initiateEmergencyClose(Counter $counter, User $teller, string $reason): EmergencyClosure
    {
        $this->validateConstraints($counter, $teller);

        $session = CounterSession::where('counter_id', $counter->id)
            ->whereDate('session_date', now()->toDateString())
            ->where('status', CounterSessionStatus::Open->value)
            ->first();
        if (! $session) {
            throw new \RuntimeException('No active session found for counter. Counter ID: '.$counter->id.', Date: '.now()->toDateString());
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

        if ($session->tellerAllocation) {
            $this->allocationService->returnToPool($session->tellerAllocation);
        }

        $this->notifyManager($closure);

        $this->auditService->logWithSeverity(
            'emergency_counter_close',
            [
                'user_id' => $teller->id,
                'entity_type' => 'EmergencyClosure',
                'entity_id' => $closure->id,
                'new_values' => [
                    'counter_code' => $counter->code,
                    'teller_id' => $teller->id,
                    'reason' => $reason,
                    'session_id' => $session->id,
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
            throw new \RuntimeException('No active session found for counter');
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
        $closure->update([
            'acknowledged_by' => $manager->id,
            'acknowledged_at' => now(),
        ]);

        $this->auditService->logWithSeverity(
            'emergency_counter_close_acknowledged',
            [
                'user_id' => $manager->id,
                'entity_type' => 'EmergencyClosure',
                'entity_id' => $closure->id,
                'new_values' => [
                    'acknowledged_by' => $manager->id,
                ],
            ],
            'INFO'
        );

        return $closure;
    }

    public function getVariance(EmergencyClosure $closure): array
    {
        $session = $closure->session;
        $counter = $closure->counter;

        $tillBalances = TillBalance::where('till_id', (string) $counter->id)
            ->where('date', $session->session_date)
            ->get();

        $variance = [];
        foreach ($tillBalances as $balance) {
            $expected = (new CounterService(
                new TellerAllocationService(
                    app(BranchPoolService::class),
                    app(MathService::class)
                ),
                app(ThresholdService::class)
            ))->calculateExpectedBalance(
                $balance->currency_code,
                $counter->id,
                $session->session_date->toDateString(),
                $balance->opening_balance
            );

            $actual = $balance->closing_balance ?? $balance->opening_balance;
            $diff = app(MathService::class)->subtract($actual, $expected);

            $variance[$balance->currency_code] = [
                'expected' => $expected,
                'actual' => $actual,
                'variance' => $diff,
            ];
        }

        return $variance;
    }
}
