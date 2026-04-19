<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class UnbalancedJournalEntriesException extends RuntimeException
{
    public function __construct(string $entryIds)
    {
        parent::__construct("Unbalanced journal entries found: {$entryIds}");
    }
}
