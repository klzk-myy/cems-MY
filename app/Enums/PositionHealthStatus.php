<?php

namespace App\Enums;

/**
 * Utilization band of a currency position against its configured limit.
 * Backing values are lowercase canonical; label() is the display form.
 */
enum PositionHealthStatus: string
{
    case Normal = 'normal';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
