<?php

namespace App\Enums;

enum MatchType: string
{
    case Levenshtein = 'levenshtein';
    case Soundex = 'soundex';
    case Metaphone = 'metaphone';

    public function label(): string
    {
        return match ($this) {
            self::Levenshtein => 'Levenshtein',
            self::Soundex => 'Soundex',
            self::Metaphone => 'Metaphone',
        };
    }
}
