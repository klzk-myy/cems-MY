<?php

namespace App\Enums;

enum AlertPriority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }

    /**
     * Map to the equivalent case priority. Centralized on the enum where
     * both values are visible — a renamed or added case fails here at
     * development time instead of silently sorting a case to the wrong
     * priority via a positional lookup table.
     */
    public function toCasePriority(): ComplianceCasePriority
    {
        return match ($this) {
            self::Critical => ComplianceCasePriority::Critical,
            self::High => ComplianceCasePriority::High,
            self::Medium => ComplianceCasePriority::Medium,
            self::Low => ComplianceCasePriority::Low,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'red',
            self::High => 'orange',
            self::Medium => 'yellow',
            self::Low => 'green',
        };
    }

    public function slaHours(): int
    {
        return match ($this) {
            self::Critical => 4,
            self::High => 8,
            self::Medium => 24,
            self::Low => 72,
        };
    }

    public static function fromRiskScore(int $score): self
    {
        return match (true) {
            $score >= 80 => self::Critical,
            $score >= 60 => self::High,
            $score >= 30 => self::Medium,
            default => self::Low,
        };
    }
}
