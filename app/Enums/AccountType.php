<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'Asset';
    case Liability = 'Liability';
    case Equity = 'Equity';
    case Revenue = 'Revenue';
    case Expense = 'Expense';
    case OffBalance = 'Off-Balance';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Asset',
            self::Liability => 'Liability',
            self::Equity => 'Equity',
            self::Revenue => 'Revenue',
            self::Expense => 'Expense',
            self::OffBalance => 'Off-Balance Sheet',
        };
    }

    public function normalBalance(): string
    {
        return match ($this) {
            self::Asset, self::Expense => 'Debit',
            self::Liability, self::Equity, self::Revenue => 'Credit',
            self::OffBalance => 'Debit',
        };
    }

    public function isDebitNormal(): bool
    {
        return $this->normalBalance() === 'Debit';
    }
}
