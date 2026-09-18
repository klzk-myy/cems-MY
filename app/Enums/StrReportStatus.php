<?php

namespace App\Enums;

/**
 * STR Report Status Enum
 *
 * Lifecycle of a Suspicious Transaction Report (pd-00 section 22):
 * Draft -> Submitted -> Acknowledged (or Rejected by BNM FIED).
 */
enum StrReportStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case Rejected = 'rejected';

    /**
     * Check if this status can transition to the target status.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Submitted,
            self::Submitted => in_array($target, [self::Acknowledged, self::Rejected], true),
            self::Acknowledged, self::Rejected => false,
        };
    }

    /**
     * Check if this status is terminal (no further transitions possible).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Acknowledged, self::Rejected], true);
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Acknowledged => 'Acknowledged',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Get the Bootstrap color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'warning',
            self::Acknowledged => 'success',
            self::Rejected => 'danger',
        };
    }
}
