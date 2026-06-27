<?php

namespace App\Enums;

/**
 * Report Generated Status Enum
 *
 * Represents the various statuses for generated reports.
 */
enum ReportGeneratedStatus: string
{
    case Pending = 'Pending';
    case Generated = 'Generated';
    case Failed = 'Failed';
    case Submitted = 'Submitted';
    case Archived = 'Archived';

    /**
     * Check if the report is pending.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if the report is generated.
     */
    public function isGenerated(): bool
    {
        return $this === self::Generated;
    }

    /**
     * Check if the report is submitted.
     */
    public function isSubmitted(): bool
    {
        return $this === self::Submitted;
    }

    /**
     * Check if the report is archived.
     */
    public function isArchived(): bool
    {
        return $this === self::Archived;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Generated => 'Generated',
            self::Failed => 'Failed',
            self::Submitted => 'Submitted',
            self::Archived => 'Archived',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Generated => 'info',
            self::Failed => 'danger',
            self::Submitted => 'success',
            self::Archived => 'secondary',
        };
    }
}
