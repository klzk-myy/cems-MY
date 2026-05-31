<?php

namespace App\Enums;

enum ReferenceType: string
{
    case Transaction = 'Transaction';
    case JournalEntry = 'JournalEntry';
    case Adjustment = 'Adjustment';
    case Manual = 'Manual';
    case FiscalYearOpening = 'FiscalYearOpening';
    case FiscalYearClosing = 'FiscalYearClosing';
    case OpeningBalance = 'Opening Balance';
    case Reversal = 'Reversal';
    case Test = 'Test';

    public function label(): string
    {
        return match ($this) {
            self::Transaction => 'Transaction',
            self::JournalEntry => 'Journal Entry',
            self::Adjustment => 'Adjustment',
            self::Manual => 'Manual Entry',
            self::FiscalYearOpening => 'Fiscal Year Opening',
            self::FiscalYearClosing => 'Fiscal Year Closing',
            self::OpeningBalance => 'Opening Balance',
            self::Reversal => 'Reversal',
            self::Test => 'Test',
        };
    }
}
