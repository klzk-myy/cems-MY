<?php

namespace App\Enums;

/**
 * system_logs.severity — uppercase by legacy convention; the CHECK/enum
 * column and the tamper-evident hash payload both carry the uppercase form.
 */
enum SystemLogSeverity: string
{
    case Info = 'INFO';
    case Warning = 'WARNING';
    case Error = 'ERROR';
    case Critical = 'CRITICAL';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Warning => 'Warning',
            self::Error => 'Error',
            self::Critical => 'Critical',
        };
    }

    /**
     * Ordering rank for min-severity filtering (scopeSeverityLevel).
     */
    public function level(): int
    {
        return match ($this) {
            self::Info => 1,
            self::Warning => 2,
            self::Error => 3,
            self::Critical => 4,
        };
    }

    /**
     * Badge variant used by the admin audit-log views.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Critical, self::Error => 'danger',
            self::Warning => 'warning',
            self::Info => 'info',
        };
    }

    public static function normalize(string $severity): self
    {
        return self::from(strtoupper($severity));
    }
}
