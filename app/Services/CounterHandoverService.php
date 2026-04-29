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
        User $incomingTeller,
        bool $verified,
        ?string $notes
    ): void {
        if ($handover->to_user_id !== $incomingTeller->id) {
            throw new UnauthorizedException('You are not the incoming teller');
        }

        if ($handover->counterSession->status !== CounterSessionStatus::PendingHandover) {
            throw new InvalidStateException('Handover is not pending acknowledgment');
        }

        $handover->counterSession->update([
            'status' => CounterSessionStatus::Open,
            'physical_count_verified' => $verified,
            'handover_notes' => $notes,
        ]);

        $handover->update(['acknowledged_at' => now()]);
    }
}
