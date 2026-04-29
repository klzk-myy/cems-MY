<?php

namespace App\Enums;

/**
 * Counter Session Status Enum
 *
 * Represents the different statuses a counter session can have.
 */
enum CounterSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case HandedOver = 'handed_over';
    case PendingHandover = 'pending_handover';
    case EmergencyClosed = 'emergency_closed';

    /**
     * Check if the session is open.
     */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * Check if the session is closed.
     */
    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    /**
     * Check if the session has been handed over.
     */
    public function isHandedOver(): bool
    {
        return $this === self::HandedOver;
    }

    public function isPendingHandover(): bool
    {
        return $this === self::PendingHandover;
    }

    public function isEmergencyClosed(): bool
    {
        return $this === self::EmergencyClosed;
    }

    /**
     * Check if the session is active (open or handed over).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::HandedOver], true);
    }

    /**
     * Check if the session can be closed.
     */
    public function canBeClosed(): bool
    {
        return $this === self::Open;
    }

    /**
     * Check if the session can be handed over.
     */
    public function canBeHandedOver(): bool
    {
        return $this === self::Open;
    }

    /**
     * Check if a new session can be started for this counter.
     */
    public function allowsNewSession(): bool
    {
        return ! $this->isOpen();
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::HandedOver => 'Handed Over',
            self::PendingHandover => 'Pending Handover',
            self::EmergencyClosed => 'Emergency Closed',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'secondary',
            self::HandedOver => 'warning',
            self::PendingHandover => 'info',
            self::EmergencyClosed => 'danger',
        };
    }

    /**
     * Get an icon name for UI display.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Open => 'door-open',
            self::Closed => 'door-closed',
            self::HandedOver => 'exchange-alt',
            self::PendingHandover => 'transfer',
            self::EmergencyClosed => 'exclamation-triangle',
        };
    }
}
