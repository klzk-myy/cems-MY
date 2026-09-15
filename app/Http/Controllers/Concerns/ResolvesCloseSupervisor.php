<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Permission;
use App\Exceptions\Domain\InvalidStateException;
use App\Models\User;

/**
 * Resolves the supervisor who may satisfy a red-variance counter close.
 *
 * CounterService::closeSession() requires a manager supervisor when closing
 * variance exceeds the red threshold — but neither close endpoint could ever
 * supply one, deadlocking the session. An acting user holding the
 * manage_counters permission satisfies the requirement themselves; anyone
 * else must name such a user via supervisor_id.
 */
trait ResolvesCloseSupervisor
{
    /**
     * @throws InvalidStateException when supervisor_id does not reference a
     *                               user holding the manage_counters permission
     */
    protected function resolveCloseSupervisor(User $user, ?int $supervisorId): ?User
    {
        if ($supervisorId !== null) {
            $supervisor = User::find($supervisorId);

            if (! $supervisor instanceof User || ! $supervisor->role->canPerform(Permission::ManageCounters)) {
                throw new InvalidStateException('supervisor_id must reference a user permitted to manage counters.');
            }

            return $supervisor;
        }

        return $user->role->canPerform(Permission::ManageCounters) ? $user : null;
    }
}
