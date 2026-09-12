<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\Domain\InvalidStateException;
use App\Models\User;

/**
 * Resolves the supervisor who may satisfy a red-variance counter close.
 *
 * CounterService::closeSession() requires a manager supervisor when closing
 * variance exceeds the red threshold — but neither close endpoint could ever
 * supply one, deadlocking the session. An acting manager/admin satisfies the
 * requirement themselves; anyone else must name a manager/admin via
 * supervisor_id (isManager() also covers Admin).
 */
trait ResolvesCloseSupervisor
{
    /**
     * @throws InvalidStateException when supervisor_id does not reference a manager/admin
     */
    protected function resolveCloseSupervisor(User $user, ?int $supervisorId): ?User
    {
        if ($supervisorId !== null) {
            $supervisor = User::find($supervisorId);

            if (! $supervisor instanceof User || ! $supervisor->isManager()) {
                throw new InvalidStateException('supervisor_id must reference a manager or admin user.');
            }

            return $supervisor;
        }

        return $user->isManager() ? $user : null;
    }
}
