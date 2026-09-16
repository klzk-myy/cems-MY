<?php

namespace App\Support;

use App\Models\User;

/**
 * The acting user, their id, and client metadata captured once at the
 * request/job edge. Services read actor identity through this type instead of
 * reaching for auth()/request() mid-method — see CLAUDE.md "Actor context".
 */
final readonly class ActorContext
{
    public function __construct(
        public ?int $userId,
        public ?string $ipAddress,
        public ?User $user = null,
        public ?string $userAgent = null,
    ) {}

    /**
     * Capture the actor from the current request context. In console/queue
     * contexts there is no authenticated user or request, so all fields are
     * null and callers fall back to the system user / no client metadata.
     */
    public static function capture(): self
    {
        $user = auth()->user();

        return new self(
            $user?->id,
            request()?->ip(),
            $user,
            request()?->userAgent(),
        );
    }

    /**
     * Actor id with the system-user fallback used across audit writes.
     */
    public function userIdOrSystem(): ?int
    {
        return $this->userId ?? config('cems.system_user_id');
    }
}
