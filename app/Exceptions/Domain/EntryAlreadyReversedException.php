<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class EntryAlreadyReversedException extends RuntimeException
{
    public function __construct(int $entryId)
    {
        parent::__construct("Entry {$entryId} has already been reversed");
    }
}
