<?php

namespace App\Enums;

/**
 * What initiated an import run (sanction_import_logs.triggered_by,
 * adverse_media_import_logs.triggered_by).
 */
enum ImportTrigger: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Manual => 'Manual',
        };
    }
}
