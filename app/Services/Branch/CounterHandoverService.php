<?php

namespace App\Services\Branch;

use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Models\CounterHandover;
use App\Models\CounterSession;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CounterHandoverService
{
    public function __construct(protected TellerAllocationService $tellerAllocationService) {}

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
                ->where('status', TellerAllocationStatus::ACTIVE)
                ->whereDate('session_date', now()->toDateString())
                ->first();

            if ($fromAllocation) {
                $this->tellerAllocationService->returnToPool($fromAllocation);
            }

            // Activate new teller's allocation
            $toAllocation = TellerAllocation::where('user_id', $locked->to_user_id)
                ->where('status', TellerAllocationStatus::APPROVED)
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
