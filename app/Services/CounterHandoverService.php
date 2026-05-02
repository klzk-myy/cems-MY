<?php

namespace App\Services;

use App\Enums\CounterSessionStatus;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Models\CounterHandover;
use App\Models\User;

class CounterHandoverService
{
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

        $handover->counterSession->update([
            'status' => CounterSessionStatus::Open,
            'physical_count_verified' => $verified,
            'handover_notes' => $notes,
        ]);

        $handover->update(['acknowledged_at' => now()]);
    }
}
