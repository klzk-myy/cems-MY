<?php

namespace App\Enums;

/**
 * Transaction Type Enum
 *
 * Represents the type of currency transaction - either buying or selling.
 */
enum TransactionType: string
{
    case Buy = 'Buy';
    case Sell = 'Sell';

    /**
     * Check if this is a buy transaction.
     */
    public function isBuy(): bool
    {
        return $this === self::Buy;
    }

    /**
     * Check if this is a sell transaction.
     */
    public function isSell(): bool
    {
        return $this === self::Sell;
    }

    /**
     * Get the opposite transaction type.
     * Used for refunds and reversals.
     */
    public function opposite(): self
    {
        return match ($this) {
            self::Buy => self::Sell,
            self::Sell => self::Buy,
        };
    }

    /**
     * Get a human-readable label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Buy',
            self::Sell => 'Sell',
        };
    }

    /**
     * Get the verb form for accounting descriptions.
     */
    public function verb(): string
    {
        return match ($this) {
            self::Buy => 'Purchase',
            self::Sell => 'Sale',
        };
    }

    /**
     * Get the action verb for UI display.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::Buy => 'Buying',
            self::Sell => 'Selling',
        };
    }
}
