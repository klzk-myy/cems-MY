<?php

namespace App\Services;

use App\Enums\CounterSessionStatus;
use App\Models\CounterSession;
use Illuminate\Database\Eloquent\Collection;

/**
 * Counter Session Service
 *
 * Handles all counter session-related business logic including:
 * - Session status checks
 * - Session duration calculations
 * - Session validation
 *
 * This service removes business logic from the CounterSession model,
 * ensuring proper MVC separation of concerns.
 */
class CounterSessionService
{
    /**
     * Check if a session is open.
     *
     * @param  CounterSession  $session  Session to check
     * @return bool True if session is open
     */
    public function isOpen(CounterSession $session): bool
    {
        return $session->status->isOpen();
    }

    /**
     * Check if a session is closed.
     *
     * @param  CounterSession  $session  Session to check
     * @return bool True if session is closed
     */
    public function isClosed(CounterSession $session): bool
    {
        return $session->status->isClosed();
    }

    /**
     * Check if a session has been handed over.
     *
     * @param  CounterSession  $session  Session to check
     * @return bool True if session is handed over
     */
    public function isHandedOver(CounterSession $session): bool
    {
        return $session->status->isHandedOver();
    }

    /**
     * Get open sessions.
     *
     * @return Collection Collection of open sessions
     */
    public function getOpenSessions(): Collection
    {
        return CounterSession::where('status', CounterSessionStatus::Open->value)
            ->with(['counter', 'user', 'tellerAllocation'])
            ->orderBy('opened_at', 'desc')
            ->get();
    }

    /**
     * Get closed sessions.
     *
     * @return Collection Collection of closed sessions
     */
    public function getClosedSessions(): Collection
    {
        return CounterSession::where('status', CounterSessionStatus::Closed->value)
            ->with(['counter', 'user', 'tellerAllocation'])
            ->orderBy('closed_at', 'desc')
            ->get();
    }

    /**
     * Get handed over sessions.
     *
     * @return Collection Collection of handed over sessions
     */
    public function getHandedOverSessions(): Collection
    {
        return CounterSession::where('status', CounterSessionStatus::HandedOver->value)
            ->with(['counter', 'user', 'tellerAllocation'])
            ->orderBy('closed_at', 'desc')
            ->get();
    }

    /**
     * Get sessions for a specific counter.
     *
     * @param  int  $counterId  Counter ID
     * @return Collection Collection of sessions
     */
    public function getSessionsForCounter(int $counterId): Collection
    {
        return CounterSession::where('counter_id', $counterId)
            ->with(['user', 'tellerAllocation'])
            ->orderBy('session_date', 'desc')
            ->orderBy('opened_at', 'desc')
            ->get();
    }

    /**
     * Get sessions for a specific date.
     *
     * @param  string  $date  Date in Y-m-d format
     * @return Collection Collection of sessions
     */
    public function getSessionsForDate(string $date): Collection
    {
        return CounterSession::where('session_date', $date)
            ->with(['counter', 'user', 'tellerAllocation'])
            ->orderBy('opened_at', 'desc')
            ->get();
    }

    /**
     * Get sessions for a specific user.
     *
     * @param  int  $userId  User ID
     * @return Collection Collection of sessions
     */
    public function getSessionsForUser(int $userId): Collection
    {
        return CounterSession::where('user_id', $userId)
            ->with(['counter', 'tellerAllocation'])
            ->orderBy('session_date', 'desc')
            ->orderBy('opened_at', 'desc')
            ->get();
    }

    /**
     * Get session duration in hours.
     *
     * @param  CounterSession  $session  Session to calculate duration for
     * @return float|null Duration in hours, or null if session is not closed
     */
    public function getDuration(CounterSession $session): ?float
    {
        if (! $session->closed_at) {
            return null;
        }

        return $session->opened_at->diffInHours($session->closed_at);
    }

    /**
     * Get open session for a specific counter.
     *
     * @param  int  $counterId  Counter ID
     * @return CounterSession|null Open session or null
     */
    public function getOpenSessionForCounter(int $counterId): ?CounterSession
    {
        return CounterSession::where('counter_id', $counterId)
            ->where('status', CounterSessionStatus::Open->value)
            ->first();
    }

    /**
     * Get open session for a specific user.
     *
     * @param  int  $userId  User ID
     * @return CounterSession|null Open session or null
     */
    public function getOpenSessionForUser(int $userId): ?CounterSession
    {
        return CounterSession::where('user_id', $userId)
            ->where('status', CounterSessionStatus::Open->value)
            ->first();
    }
}
