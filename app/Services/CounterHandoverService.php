<?php

namespace App\Services;

use App\Enums\CounterSessionStatus;
use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Models\CounterHandover;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CounterHandoverService
{
    public function findPendingHandover(int $userId, int $counterId, string $date): ?CounterHandover
    {
        return CounterHandover::with(['counterSession', 'fromUser', 'supervisor'])
            ->whereHas('counterSession', function ($query) use ($counterId, $date) {
                $query->where('counter_id', $counterId)
                    ->whereDate('session_date', $date);
            })
            ->where('to_user_id', $userId)
            ->whereNull('acknowledged_at')
            ->first();
    }

    public function acknowledgeHandover(
        CounterHandover $handover,
        User $user,
        bool $verified,
        ?string $notes
    ): void {
        // Only managers can acknowledge handovers (S2 compliance)
        if (! $user->isManager()) {
            throw new UnauthorizedException('Only managers can acknowledge handovers');
        }

        if ($handover->counterSession->status !== CounterSessionStatus::PendingHandover) {
            throw new InvalidStateException('Handover is not pending acknowledgment');
        }

        // Yellow variance requires explicit acknowledgment (S7)
        if ($handover->yellow_variance && ! $verified) {
            throw new InvalidStateException('Yellow variance requires acknowledgment');
        }

        DB::transaction(function () use ($handover, $verified, $notes) {
            // Return previous teller's allocation to branch pool
            $fromAllocation = TellerAllocation::where('user_id', $handover->from_user_id)
                ->where('status', TellerAllocationStatus::ACTIVE)
                ->whereDate('session_date', now()->toDateString())
                ->first();

            if ($fromAllocation) {
                $tellerAllocationService = app(TellerAllocationService::class);
                $tellerAllocationService->returnToPool($fromAllocation);
            }

            // Activate new teller's allocation
            $toAllocation = TellerAllocation::where('user_id', $handover->to_user_id)
                ->where('status', TellerAllocationStatus::APPROVED)
                ->whereDate('session_date', now()->toDateString())
                ->first();

            if ($toAllocation) {
                $tellerAllocationService = app(TellerAllocationService::class);
                $tellerAllocationService->activateAllocation($toAllocation);
            }

            $handover->counterSession->update([
                'status' => CounterSessionStatus::HandedOver,
                'physical_count_verified' => $verified,
                'handover_notes' => $notes,
            ]);

            $handover->update(['acknowledged_at' => now()]);
        });
    }
}
