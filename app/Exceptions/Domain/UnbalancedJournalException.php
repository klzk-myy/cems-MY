<?php

namespace App\Exceptions\Domain;

class UnbalancedJournalException extends DomainException
{
    public function __construct(string $debits, string $credits)
    {
        parent::__construct(
            "Journal entry is not balanced: debits ({$debits}) do not equal credits ({$credits})"
        );
    }
}
