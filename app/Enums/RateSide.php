<?php

namespace App\Enums;

/**
 * Which side of an exchange-rate card applies. Distinct from
 * {@see TransactionType}: rate cards also expose a 'mid' reference rate
 * (revaluation, trend analysis) that has no transaction counterpart.
 */
enum RateSide: string
{
    case Buy = 'buy';
    case Sell = 'sell';
    case Mid = 'mid';

    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Buy',
            self::Sell => 'Sell',
            self::Mid => 'Mid',
        };
    }

    public static function fromTransactionType(TransactionType $type): self
    {
        return match ($type) {
            TransactionType::Buy => self::Buy,
            TransactionType::Sell => self::Sell,
        };
    }
}
