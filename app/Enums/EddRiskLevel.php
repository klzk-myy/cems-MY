<?php

namespace App\Enums;

enum EddRiskLevel: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';
    case Critical = 'Critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low Risk',
            self::Medium => 'Medium Risk',
            self::High => 'High Risk',
            self::Critical => 'Critical Risk',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'success',
            self::Medium => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }
}
