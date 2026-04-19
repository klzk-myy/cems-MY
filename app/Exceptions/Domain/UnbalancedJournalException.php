<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class UnbalancedJournalException extends InvalidArgumentException
{
    public function __construct(string $debits, string $credits)
    {
        parent::__construct(
            "Journal entry is not balanced: debits ({$debits}) do not equal credits ({$credits})"
        );
    }
}
