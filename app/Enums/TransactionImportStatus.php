<?php

namespace App\Enums;

/**
 * Transaction Import Status Enum
 *
 * Represents the various statuses a transaction import can have.
 */
enum TransactionImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';

    /**
     * Check if the import is pending.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if the import is processing.
     */
    public function isProcessing(): bool
    {
        return $this === self::Processing;
    }

    /**
     * Check if the import is completed (with or without errors).
     */
    public function isCompleted(): bool
    {
        return in_array($this, [self::Completed, self::CompletedWithErrors], true);
    }

    /**
     * Check if the import has errors.
     */
    public function hasErrors(): bool
    {
        return $this === self::CompletedWithErrors || $this === self::Failed;
    }

    /**
     * Check if the import failed.
     */
    public function isFailed(): bool
    {
        return $this === self::Failed;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::CompletedWithErrors => 'Completed with Errors',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Processing => 'warning',
            self::Completed => 'success',
            self::CompletedWithErrors => 'warning',
            self::Failed => 'danger',
        };
    }
}
