<?php

namespace App\Enums;

/**
 * Recalculation Trigger Enum
 *
 * Represents what triggers a compliance finding recalculation.
 */
enum RecalculationTrigger: string
{
    case Manual = 'Manual';
    case Scheduled = 'Scheduled';
    case EventDriven = 'Event_Driven';

    /**
     * Get a human-readable label for the trigger.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Scheduled => 'Scheduled',
            self::EventDriven => 'Event-Driven',
        };
    }
}
