<?php

namespace App\Exceptions\Domain;

class UnbalancedJournalEntriesException extends DomainException
{
    public function __construct(string $entryIds)
    {
        parent::__construct("Unbalanced journal entries found: {$entryIds}");
    }
}
