<?php

namespace App\Enums;

enum ReferenceType: string
{
    case Transaction = 'Transaction';
    case JournalEntry = 'JournalEntry';
    case Adjustment = 'Adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Transaction => 'Transaction',
            self::JournalEntry => 'Journal Entry',
            self::Adjustment => 'Adjustment',
        };
    }
}
