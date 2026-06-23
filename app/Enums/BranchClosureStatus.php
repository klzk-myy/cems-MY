<?php

namespace App\Enums;

/**
 * Branch Closure Status Enum
 *
 * Represents the various statuses in the branch closure workflow.
 */
enum BranchClosureStatus: string
{
    case Initiated = 'initiated';
    case Settled = 'settled';
    case Finalized = 'finalized';

    /**
     * Check if the workflow is initiated.
     */
    public function isInitiated(): bool
    {
        return $this === self::Initiated;
    }

    /**
     * Check if the workflow is settled.
     */
    public function isSettled(): bool
    {
        return $this === self::Settled;
    }

    /**
     * Check if the workflow is finalized.
     */
    public function isFinalized(): bool
    {
        return $this === self::Finalized;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Initiated',
            self::Settled => 'Settled',
            self::Finalized => 'Finalized',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Initiated => 'warning',
            self::Settled => 'info',
            self::Finalized => 'success',
        };
    }
}
