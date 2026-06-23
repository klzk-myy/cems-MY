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
