<?php

namespace App\Services\Concerns;

use App\Models\SystemLog;

trait AuditsMfaSession
{
    /**
     * Log MFA (Multi-Factor Authentication) events.
     *
     * @param  string  $action  MFA action (mfa_setup_started, mfa_setup_completed,
     *                          mfa_verification_success, mfa_verification_failed,
     *                          mfa_disable_requested, mfa_disable_completed,
     *                          mfa_recovery_code_used, mfa_trusted_device_added,
     *                          mfa_trusted_device_removed)
     * @param  int|null  $userId  User ID (null if not authenticated)
     * @param  array  $data  Additional context data
     */
    public function logMfaEvent(string $action, ?int $userId = null, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'mfa_verification_failed', 'mfa_disable_requested', 'mfa_recovery_code_used',
            'mfa_trusted_device_removed' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'user_id' => $userId ?? auth()->id(),
                'entity_type' => 'MfaEvent',
                'entity_id' => $data['entity_id'] ?? null,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log session events.
     *
     * @param  string  $action  Session action (session_timeout,
     *                          session_extended, session_concurrent_blocked)
     * @param  array  $data  Session data
     */
    public function logSessionEvent(string $action, array $data = []): SystemLog
    {
        $severity = match ($action) {
            'session_concurrent_blocked' => 'WARNING',
            default => 'INFO',
        };

        return $this->logWithSeverity(
            $action,
            [
                'user_id' => $data['user_id'] ?? auth()->id(),
                'entity_type' => 'Session',
                'entity_id' => $data['session_id'] ?? null,
                'old_values' => $data['old'] ?? [],
                'new_values' => $data['new'] ?? [],
            ],
            $severity
        );
    }

    /**
     * Log permission denied events.
     *
     * @param  string  $resource  Resource being accessed
     * @param  string  $action  Action attempted
     * @param  string  $reason  Reason for denial
     * @param  array  $data  Additional context
     */
    public function logPermissionDenied(string $resource, string $action, string $reason, array $data = []): SystemLog
    {
        return $this->logWithSeverity(
            'permission_denied',
            [
                'user_id' => auth()->id(),
                'entity_type' => $resource,
                'entity_id' => $data['entity_id'] ?? null,
                'new_values' => [
                    'action' => $action,
                    'reason' => $reason,
                    'resource' => $resource,
                    'attempted_at' => now()->toIso8601String(),
                ],
            ],
            'WARNING'
        );
    }
}
