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

    /**
     * Apply the account's natural sign to a net (debit − credit) amount, so
     * credit-normal accounts report positive balances. Every statement and
     * closing path uses this — do not re-derive the rule by hand.
     */
    public function applyNaturalSign(string $net): string
    {
        if ($this->isDebitNormal()) {
            return $net;
        }

        if (! is_numeric($net)) {
            throw new \InvalidArgumentException('Net amount must be numeric.');
        }

        // Scale 4 matches decimal(18,4) storage, so a flipped balance keeps the
        // trailing zeros the call sites previously got from bcmul(..., 4).
        return bcsub('0', $net, 4);
    }
}
